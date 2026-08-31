<?php

declare(strict_types=1);

namespace App\Tests\Controller\Shop;

use PHPUnit\Framework\TestCase;

final class BrandControllerTest extends TestCase
{
    public function testRoutesAndCardnextProductCardAreUsed(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../src/Controller/Shop/BrandController.php');
        $detail = file_get_contents(__DIR__ . '/../../../templates/shop/brand/show.html.twig');
        self::assertIsString($controller);
        self::assertIsString($detail);

        self::assertStringContainsString("/{_locale}/marken'", $controller);
        self::assertStringContainsString("/{_locale}/marken/{slug}'", $controller);
        self::assertStringContainsString("component('cardnext:product:card'", $detail);
        self::assertStringNotContainsString('@SyliusShop/product/common/card.html.twig', $detail);
    }

    public function testBrandTemplatesNeverRenderProductCounts(): void
    {
        foreach (['index.html.twig', 'show.html.twig'] as $template) {
            $contents = file_get_contents(__DIR__ . '/../../../templates/shop/brand/' . $template);
            self::assertIsString($contents);
            self::assertStringNotContainsString('productCount', $contents);
            self::assertDoesNotMatchRegularExpression('/\{\{\s*(total|count)\s*\}\}/', $contents);
        }
    }

    public function testCatalogQueryIsChannelAndAvailabilityAware(): void
    {
        $catalog = file_get_contents(__DIR__ . '/../../../src/Service/BrandCatalog.php');
        self::assertIsString($catalog);
        self::assertStringContainsString('c.code = :channel', $catalog);
        self::assertStringContainsString('p.enabled = 1', $catalog);
        self::assertStringContainsString('v.enabled = 1', $catalog);
        self::assertStringContainsString('cp.price IS NOT NULL', $catalog);
    }

    public function testBrandPagesUseTheSharedBreadcrumbStructure(): void
    {
        $index = file_get_contents(__DIR__ . '/../../../templates/shop/brand/index.html.twig');
        $detail = file_get_contents(__DIR__ . '/../../../templates/shop/brand/show.html.twig');
        $css = file_get_contents(__DIR__ . '/../../../assets/shop/styles/cardnext.css');
        self::assertIsString($index);
        self::assertIsString($detail);
        self::assertIsString($css);

        foreach ([$index, $detail] as $template) {
            self::assertStringContainsString('cn-container cn-breadcrumbs__inner', $template);
            self::assertStringContainsString('cn-breadcrumbs__sep', $template);
            self::assertStringContainsString('aria-hidden="true"', $template);
            self::assertStringContainsString('aria-current="page"', $template);
        }

        self::assertStringNotContainsString('.cn-brand-detail .cn-breadcrumbs', $css);
    }
}
