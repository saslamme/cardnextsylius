<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class CmsProductSliderTemplateTest extends TestCase
{
    public function testCmsSliderReusesTheHomepageSliderAndProductCard(): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../../templates/shop/cms/block/_product_slider.html.twig');

        self::assertStringContainsString('cardnext_cms_product_slider_products', $template);
        self::assertStringContainsString('products is not empty', $template);
        self::assertStringContainsString('data-cn-product-slider', $template);
        self::assertStringContainsString('data-cn-product-slider-viewport', $template);
        self::assertStringContainsString('cn-product-slider__track', $template);
        self::assertStringContainsString('cn-product-slider__slide', $template);
        self::assertStringContainsString("component('cardnext:product:card'", $template);
        self::assertStringContainsString('config.showNavigation|default(true)', $template);
        self::assertStringContainsString('data-cn-product-slider-previous', $template);
        self::assertStringContainsString('data-cn-product-slider-next', $template);
    }

    public function testCmsProductSelectionUsesRemoteAutocompleteAndStableCodes(): void
    {
        $type = (string) file_get_contents(__DIR__ . '/../../src/Form/Cms/CmsProductSelectionType.php');

        self::assertStringContainsString('#[AsEntityAutocompleteField]', $type);
        self::assertStringContainsString("'translations.name'", $type);
        self::assertStringContainsString("'variants.code'", $type);
        self::assertStringContainsString("'variants.manufacturerPartNumber'", $type);
        self::assertStringContainsString("'variants.gtin'", $type);
        self::assertStringContainsString('array_keys($codes)', $type);
    }
}
