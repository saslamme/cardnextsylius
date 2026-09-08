<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class HomepageBrandVariantsTemplateTest extends TestCase
{
    public function testHomepageUsesNormalizedThemeAndPassesItToEveryRenderer(): void
    {
        $template = $this->read('templates/bundles/SyliusShopBundle/homepage/index.html.twig');

        self::assertStringContainsString('{% set brandTheme = cardnext_homepage_theme() %}', $template);
        self::assertStringContainsString('cardnext-cms--brand-{{ brandTheme }}', $template);
        self::assertSame(2, substr_count($template, 'brandTheme: brandTheme'));
        self::assertStringNotContainsString("channel.code == 'IDENTIBLE_DE'", $template);
        self::assertStringNotContainsString("channel.code == 'INPLASTOR_AT'", $template);
    }

    public function testBothAlternativeBrandsHaveScopedLayoutRules(): void
    {
        $css = $this->read('assets/shop/styles/cardnext.css');

        self::assertStringContainsString('.cardnext-cms--brand-identible .cn-hero', $css);
        self::assertStringContainsString('.cardnext-cms--brand-identible .cn-home-category', $css);
        self::assertStringContainsString('.cardnext-cms--brand-inplastor .cn-hero', $css);
        self::assertStringContainsString('.cardnext-cms--brand-inplastor .cn-home-industries', $css);
        self::assertStringContainsString('Cardnext intentionally continues to use the default rules above', $css);
    }

    public function testAlternativeBrandMediaUsesControlledProportions(): void
    {
        $css = $this->read('assets/shop/styles/cardnext.css');

        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-identible \.cn-hero__grid \{[^}]*grid-template-columns: minmax\(0,3fr\) minmax\(360px,2fr\);/', $css);
        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-identible \.cn-hero__visual \{[^}]*max-height: 440px;[^}]*aspect-ratio: 4 \/ 3;/', $css);
        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-identible \.cn-home-service__photo \{[^}]*max-height: 440px;[^}]*aspect-ratio: 4 \/ 5;/', $css);
        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-identible \.cn-home-industries \.cn-card__media--photo \{ aspect-ratio: 16 \/ 9; \}/', $css);
        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-inplastor \.cn-hero__visual \{[^}]*max-height: 460px;[^}]*aspect-ratio: 16 \/ 10;/', $css);
        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-inplastor \.cn-section--soft \.cn-card__media--photo \{ aspect-ratio: 16 \/ 9; \}/', $css);
    }

    public function testIdentibleUsesLightSurfacesWithOnlyTheClosingCtaDark(): void
    {
        $css = $this->read('assets/shop/styles/cardnext.css');

        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-identible \.cn-hero \{[^}]*color: var\(--cn-ink\);[^}]*background: #f1f4f7;/', $css);
        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-identible \.cn-home-service \{[^}]*color: var\(--cn-ink\);[^}]*background: #f4f6f8;/', $css);
        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-identible \.cn-home-service__list \{[^}]*background: #fff;[^}]*border: 1px solid var\(--cn-border\);/', $css);
        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-identible \.cn-home-promo \{[^}]*color: var\(--cn-ink\);[^}]*background: #fff;[^}]*border: 1px solid var\(--cn-border\);/', $css);
        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-identible \.cn-cta \{ background: #151b20; \}/', $css);

        self::assertStringNotContainsString('.cardnext-cms--brand-cardnext .cn-hero', $css);
        self::assertMatchesRegularExpression('/\.cardnext-cms--brand-inplastor \.cn-hero \{ border-block: 1px solid var\(--cn-ink\); \}/', $css);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($contents);

        return $contents;
    }
}
