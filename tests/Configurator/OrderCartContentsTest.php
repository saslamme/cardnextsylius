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
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/bundles/SyliusShopBundle/cart/index/content/form/sections/general/items/body.html.twig');
        self::assertIsString($template);

        self::assertStringContainsString('{% set cart = hookable_metadata.context.resource %}', $template);
        self::assertStringContainsString('{% for item in cart.configuredItems %}', $template);
        self::assertStringContainsString('{{ item.configuratorName }}', $template);
        self::assertStringContainsString('value="{{ item.quantity }}"', $template);
        self::assertStringContainsString('item.selectionsSnapshot', $template);
        self::assertStringContainsString('entry.values|map(value => value.name)|join', $template);
        self::assertStringContainsString('{{ entry.value.name }}', $template);
        self::assertStringContainsString('{{ entry.value }}', $template);
        self::assertStringContainsString('item.leadTimeName', $template);
        self::assertStringContainsString('item.workingDays', $template);
        self::assertStringContainsString('item.unitAmount|sylius_format_money', $template);
        self::assertStringContainsString('item.total|sylius_format_money', $template);
        self::assertStringContainsString("path('cardnext_shop_configured_item_quantity'", $template);
        self::assertStringContainsString("path('cardnext_shop_configured_item_remove'", $template);
        self::assertStringNotContainsString('<form', $template);
        self::assertStringNotContainsString('cardnext_shop_configurator_page', $template);
    }

    public function testConfiguredRowsPreserveStandardSyliusCartRowsForMixedCarts(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/bundles/SyliusShopBundle/cart/index/content/form/sections/general/items/body.html.twig');
        self::assertIsString($template);

        self::assertStringContainsString('hookable_metadata.context.form.items', $template);
        self::assertStringContainsString("{% hook 'body' with { form: form_item, item, index } %}", $template);
        self::assertStringContainsString('data-configured-cart-items', $template);
    }

    public function testCartSummarySeparatesConfiguredItemsFromSyliusItemsSubtotal(): void
    {
        $root = dirname(__DIR__, 2);
        $itemsTotal = file_get_contents($root . '/templates/bundles/SyliusShopBundle/cart/index/content/form/sections/general/summary/items_total.html.twig');
        $configuredTotal = file_get_contents($root . '/templates/shop/cart/configured_items_total.html.twig');
        self::assertIsString($itemsTotal);
        self::assertIsString($configuredTotal);

        self::assertStringContainsString('{% if cart.itemsSubtotal > 0 %}', $itemsTotal);
        self::assertStringContainsString('configuredItemsTotal + item.total', $configuredTotal);
        self::assertStringContainsString('Konfigurierte Artikel:', $configuredTotal);
        self::assertStringNotContainsString('setItemsTotal', $configuredTotal);
    }

    public function testConfiguredCartActionsUseJavascriptWithoutNestedForms(): void
    {
        $javascript = file_get_contents(dirname(__DIR__, 2) . '/assets/shop/cardnext.js');
        self::assertIsString($javascript);

        self::assertStringContainsString("fetch(endpoint", $javascript);
        self::assertStringContainsString("body.set('quantity', quantity)", $javascript);
        self::assertStringContainsString("window.location.reload()", $javascript);
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
