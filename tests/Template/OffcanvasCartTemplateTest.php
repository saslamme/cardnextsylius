<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class OffcanvasCartTemplateTest extends TestCase
{
    public function testConfiguredItemsOverrideTheSyliusOffcanvasHooks(): void
    {
        $root = dirname(__DIR__, 2);
        $hooks = Yaml::parseFile($root . '/config/packages/cardnext_twig_hooks.yaml')['sylius_twig_hooks']['hooks'];

        self::assertSame(
            'shop/layout/offcanvas/cart/body/items.html.twig',
            $hooks['sylius_shop.base.offcanvas.cart.body']['items']['template'],
        );
        self::assertSame(
            'shop/layout/offcanvas/cart/footer.html.twig',
            $hooks['sylius_shop.base.offcanvas.cart']['footer']['template'],
        );
    }

    public function testItemsTemplateHandlesRegularConfiguredAndEmptyCarts(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/shop/layout/offcanvas/cart/body/items.html.twig');
        self::assertIsString($template);

        self::assertStringContainsString('cart.items|length == 0 and cart.configuredItems|length == 0', $template);
        self::assertStringContainsString('for item in cart.items', $template);
        self::assertStringContainsString("hook 'items' with { item }", $template);
        self::assertStringContainsString('for item in cart.configuredItems', $template);
        self::assertStringContainsString('item.quantity }} Stück', $template);
        self::assertStringContainsString('item.total|sylius_format_money(item.currencyCode, item.localeCode)', $template);
    }

    public function testFooterUsesTheNativeOrderTotal(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/shop/layout/offcanvas/cart/footer.html.twig');
        self::assertIsString($template);

        self::assertStringContainsString('money.convertAndFormat(cart.total)', $template);
        self::assertStringNotContainsString('cart.itemsTotal + configuredItemsTotal', $template);
        self::assertStringContainsString("path('sylius_shop_cart_summary')", $template);
        self::assertStringContainsString("path('sylius_shop_checkout_start')", $template);
    }
}
