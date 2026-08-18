<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Order\ConfiguredOrderItem;
use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use PHPUnit\Framework\TestCase;

final class OrderCartContentsTest extends TestCase
{
    public function testNewOrderIsEmpty(): void
    {
        self::assertTrue((new Order())->isEmpty());
    }

    public function testOrderWithOnlyConfiguredItemIsNotEmpty(): void
    {
        $order = new Order();
        $order->addConfiguredItem($this->configuredItem());

        self::assertFalse($order->isEmpty());
    }

    public function testOrderWithOnlyStandardItemIsNotEmpty(): void
    {
        $order = new Order();
        $order->addItem(new OrderItem());

        self::assertFalse($order->isEmpty());
    }

    public function testOrderWithStandardAndConfiguredItemsIsNotEmpty(): void
    {
        $order = new Order();
        $order->addItem(new OrderItem());
        $order->addConfiguredItem($this->configuredItem());

        self::assertFalse($order->isEmpty());
    }

    public function testOrderIsEmptyAfterItsLastConfiguredItemIsRemoved(): void
    {
        $order = new Order();
        $configuredItem = $this->configuredItem();
        $order->addConfiguredItem($configuredItem);

        $order->removeConfiguredItem($configuredItem);

        self::assertTrue($order->isEmpty());
    }

    public function testCartTemplateRendersConfiguredItemDetailsAndActions(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/bundles/SyliusShopBundle/cart/index/content/form/sections/general.html.twig');
        self::assertIsString($template);

        self::assertStringContainsString('{% for item in cart.configuredItems %}', $template);
        self::assertStringContainsString('{{ item.configuratorName }}', $template);
        self::assertStringContainsString('{{ item.quantity }}', $template);
        self::assertStringContainsString('item.selectionsSnapshot', $template);
        self::assertStringContainsString('item.leadTimeName', $template);
        self::assertStringContainsString('item.total|sylius_format_money', $template);
        self::assertStringContainsString("path('cardnext_shop_configured_item_quantity'", $template);
        self::assertStringContainsString("path('cardnext_shop_configured_item_remove'", $template);
    }

    private function configuredItem(): ConfiguredOrderItem
    {
        return new ConfiguredOrderItem(
            'cards',
            'Cards',
            'de_DE',
            'WEB',
            'EUR',
            1,
            str_repeat('a', 64),
            [],
            [],
            [],
            100,
            0,
            100,
            100,
            0,
            0,
            100,
        );
    }
}
