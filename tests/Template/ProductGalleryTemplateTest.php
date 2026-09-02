<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class ProductGalleryTemplateTest extends TestCase
{
    public function testGalleryDoesNotRenderTheMainTaxonBadge(): void
    {
        $root = \dirname(__DIR__, 2);
        $template = (string) file_get_contents($root . '/templates/bundles/SyliusShopBundle/product/show/content/info/overview/images.html.twig');
        $styles = (string) file_get_contents($root . '/assets/shop/styles/cardnext.css');

        self::assertStringNotContainsString('product.mainTaxon', $template);
        self::assertStringNotContainsString('cn-gallery__badge', $template);
        self::assertStringNotContainsString('.cn-gallery__badge', $styles);
    }
}
