<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class HomepageProductSliderTemplateTest extends TestCase
{
    public function testHomepageUsesDedicatedProgressiveSlider(): void
    {
        $homepage = (string) file_get_contents(__DIR__ . '/../../templates/bundles/SyliusShopBundle/homepage/index.html.twig');
        $list = (string) file_get_contents(__DIR__ . '/../../templates/shop/homepage/product_list.html.twig');

        self::assertStringContainsString('cardnext_homepage_products(10)', $homepage);
        self::assertStringContainsString('data-cn-product-slider', $homepage);
        self::assertStringContainsString('data-cn-product-slider-previous', $homepage);
        self::assertStringContainsString('data-cn-product-slider-next', $homepage);
        self::assertStringContainsString('data-cn-product-slider-viewport', $list);
        self::assertStringContainsString('cn-product-slider__track', $list);
        self::assertStringContainsString("component('cardnext:product:card'", $list);
        self::assertStringNotContainsString('cn-product-grid', $list);
    }

    public function testSliderUsesNativeScrollingWithoutAutoplay(): void
    {
        $javascript = (string) file_get_contents(__DIR__ . '/../../assets/shop/homepage-product-slider.js');
        $stylesheet = (string) file_get_contents(__DIR__ . '/../../assets/shop/styles/cardnext.css');

        self::assertStringContainsString('scrollBy({', $javascript);
        self::assertStringContainsString('getBoundingClientRect().width + gap', $javascript);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $javascript);
        self::assertStringNotContainsString('autoplay', strtolower($javascript));
        self::assertMatchesRegularExpression('/\.cn-product-slider__viewport \{[^}]*overflow-x: auto;[^}]*scroll-snap-type: x mandatory;/s', $stylesheet);
        self::assertStringContainsString('/ 4)', $stylesheet);
        self::assertStringContainsString('/ 1.2)', $stylesheet);
    }

    public function testCatalogGridStylesRemainAvailable(): void
    {
        $stylesheet = (string) file_get_contents(__DIR__ . '/../../assets/shop/styles/cardnext.css');

        self::assertStringContainsString('.cn-product-grid { display: grid;', $stylesheet);
        self::assertStringContainsString('.cn-product-grid--4 { grid-template-columns:', $stylesheet);
    }
}
