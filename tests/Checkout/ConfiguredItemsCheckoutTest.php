<?php

declare(strict_types=1);

namespace App\Tests\Checkout;

use App\Entity\Order\ConfiguredOrderItem;
use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Product\ProductVariant;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ConfiguredItemsCheckoutTest extends TestCase
{
    public function testConfiguredOnlyShippingRequirementFollowsSnapshotFlag(): void
    {
        $shippingOrder = new Order();
        $shippingOrder->addConfiguredItem($this->configuredItem(true));
        self::assertTrue($shippingOrder->isShippingRequired());

        $digitalOrder = new Order();
        $digitalOrder->addConfiguredItem($this->configuredItem(false));
        self::assertFalse($digitalOrder->isShippingRequired());
    }

    public function testAnyConfiguredItemCanRequireShipping(): void
    {
        $order = new Order();
        $order->addConfiguredItem($this->configuredItem(false));
        $order->addConfiguredItem($this->configuredItem(true));

        self::assertTrue($order->isShippingRequired());
    }

    public function testPhysicalSyliusItemStillRequiresShipping(): void
    {
        $variant = new ProductVariant();
        $variant->setShippingRequired(true);
        $item = new OrderItem();
        $item->setVariant($variant);

        $order = new Order();
        $order->addItem($item);
        $order->addConfiguredItem($this->configuredItem(false));

        self::assertTrue($order->isShippingRequired());
    }

    public function testCheckoutHooksExposeCompactAndDetailedConfiguredItems(): void
    {
        $root = \dirname(__DIR__, 2);
        $configuration = Yaml::parseFile($root . '/config/packages/cardnext_twig_hooks.yaml');
        self::assertIsArray($configuration);
        self::assertIsArray($configuration['sylius_twig_hooks']);
        self::assertIsArray($configuration['sylius_twig_hooks']['hooks']);
        $hooks = $configuration['sylius_twig_hooks']['hooks'];
        self::assertIsArray($hooks['sylius_shop.checkout.common.sidebar.summary.items']);
        self::assertIsArray($hooks['sylius_shop.shared.order.show.summary.table']);

        self::assertArrayHasKey('configured_items', $hooks['sylius_shop.checkout.common.sidebar.summary.items']);
        self::assertArrayHasKey('configured_items', $hooks['sylius_shop.shared.order.show.summary.table']);

        $sidebar = (string) file_get_contents($root . '/templates/shop/checkout/sidebar/configured_items.html.twig');
        self::assertStringContainsString('Konfiguriertes Produkt', $sidebar);
        self::assertStringNotContainsString('selectionsSnapshot', $sidebar);

        $review = (string) file_get_contents($root . '/templates/shop/checkout/complete/configured_items.html.twig');
        self::assertStringContainsString('item.selectionsSnapshot', $review);
        self::assertStringContainsString('item.unitAmount', $review);
        self::assertStringContainsString('item.total', $review);
    }

    public function testCheckoutFinalTotalsAreNeverRecalculatedInTemplates(): void
    {
        $root = \dirname(__DIR__, 2);
        $templates = [
            $root . '/templates/shop/layout/offcanvas/cart/footer.html.twig',
            $root . '/templates/shop/checkout/sidebar/configured_items_total.html.twig',
            $root . '/templates/shop/checkout/complete/configured_items_total.html.twig',
        ];

        foreach ($templates as $template) {
            self::assertStringNotContainsString('order.total +', (string) file_get_contents($template));
            self::assertStringNotContainsString('cart.itemsTotal +', (string) file_get_contents($template));
        }
    }

    private function configuredItem(bool $shippingRequired): ConfiguredOrderItem
    {
        return new ConfiguredOrderItem(
            'cards',
            'Plastikkarten bedrucken',
            'de_DE',
            'WEB',
            'EUR',
            250,
            str_repeat('a', 64),
            [],
            [],
            [],
            100,
            0,
            100,
            25000,
            0,
            0,
            25000,
            null,
            'Standard',
            5,
            null,
            $shippingRequired,
        );
    }
}
