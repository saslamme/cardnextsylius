<?php

declare(strict_types=1);

namespace App\Tests\ProductComparison;

use PHPUnit\Framework\TestCase;

final class ProductComparisonArchitectureTest extends TestCase
{
    public function testShareableRouteAndChannelFilteringArePresent(): void
    {
        $route = file_get_contents(__DIR__ . '/../../config/routes/zz_cardnext_shop.yaml');
        $service = file_get_contents(__DIR__ . '/../../src/Service/ProductComparisonService.php');
        self::assertIsString($route);
        self::assertIsString($service);

        self::assertStringContainsString('cardnext_shop_product_compare:', $route);
        self::assertStringContainsString('/produktvergleich', $route);
        self::assertStringContainsString('p.enabled = true', $service);
        self::assertStringContainsString('v.enabled = true', $service);
        self::assertStringContainsString('cp.channelCode = :channelCode', $service);
        self::assertStringContainsString("innerJoin('p.channels'", $service);
    }

    public function testSharedCardAndDetailUseTheSameComparisonControl(): void
    {
        $card = file_get_contents(__DIR__ . '/../../templates/shop/category/product_card.html.twig');
        $detail = file_get_contents(__DIR__ . '/../../templates/bundles/SyliusShopBundle/product/show/content/info/summary.html.twig');
        self::assertIsString($card);
        self::assertIsString($detail);

        self::assertStringContainsString('data-compare-toggle', $card);
        self::assertStringContainsString('data-compare-toggle', $detail);
        self::assertStringContainsString('data-product-code', $card);
    }

    public function testAccessibleTableNoindexAndDifferenceToggleArePresent(): void
    {
        $template = file_get_contents(__DIR__ . '/../../templates/shop/product_compare/index.html.twig');
        self::assertIsString($template);

        self::assertStringContainsString('noindex,follow', $template);
        self::assertStringContainsString('<table', $template);
        self::assertStringContainsString('scope="row"', $template);
        self::assertStringContainsString('scope="col"', $template);
        self::assertStringContainsString('data-compare-differences', $template);
    }

    public function testSelectionIsLocalOnlyAndLimitedToThree(): void
    {
        $javascript = file_get_contents(__DIR__ . '/../../assets/shop/product-comparison.js');
        self::assertIsString($javascript);

        self::assertStringContainsString('const MAX_PRODUCTS = 3', $javascript);
        self::assertStringContainsString('window.localStorage', $javascript);
        self::assertStringContainsString('aria-live', $javascript);
    }
}
