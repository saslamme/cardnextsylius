<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class CmsManufacturerSliderAndGalleryTemplateTest extends TestCase
{
    public function testManufacturerTemplateContainsAccessibleSliderAndBrandFallback(): void
    {
        $template = file_get_contents(__DIR__ . '/../../templates/shop/cms/block/_manufacturer_slider.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString('data-cn-manufacturer-slider', $template);
        self::assertStringContainsString('cardnext_shop_brand_show', $template);
        self::assertStringContainsString('manufacturer.logoPath', $template);
        self::assertStringContainsString('cn-manufacturer-card__name', $template);
        self::assertStringContainsString('aria-label="Vorherige Hersteller"', $template);
    }

    public function testGalleryTemplateHasSemanticResponsiveMarkupAndAltText(): void
    {
        $template = file_get_contents(__DIR__ . '/../../templates/shop/cms/block/_gallery.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString('<figure', $template);
        self::assertStringContainsString('<figcaption', $template);
        self::assertStringContainsString("alt=\"{{ item.alt|default('') }}\"", $template);
        self::assertStringContainsString('cn-gallery--{{ columns }}', $template);
    }
}
