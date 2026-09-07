<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class HomepageCmsDesignTemplateTest extends TestCase
{
    public function testPromosAreGroupedOnceByHomepageRenderer(): void
    {
        $homepage = $this->template('templates/bundles/SyliusShopBundle/homepage/index.html.twig');
        self::assertStringContainsString("block.type == 'homepage_promo'", $homepage);
        self::assertStringContainsString('_homepage_promos.html.twig', $homepage);
        self::assertStringContainsString('promosRendered', $homepage);
        $promos = $this->template('templates/shop/cms/block/_homepage_promos.html.twig');
        self::assertStringContainsString('cn-home-promos__grid', $promos);
        self::assertStringContainsString('cn-home-promo__media', $promos);
        self::assertStringNotContainsString('cn-card--promo', $promos);
    }

    public function testHomepageSectionsUseLegacyResponsiveClasses(): void
    {
        $industries = $this->template('templates/shop/cms/block/_homepage_industries.html.twig');
        self::assertSame(1, substr_count($industries, 'cn-grid cn-grid--3'));
        self::assertStringContainsString('cn-card__media cn-card__media--photo', $industries);
        $service = $this->template('templates/shop/cms/block/_homepage_service.html.twig');
        self::assertStringContainsString('cn-home-service__list', $service);
        self::assertStringContainsString('config.items', $service);
    }

    public function testHomepageVariantsDoNotReplaceGenericFeatureMarkup(): void
    {
        $features = $this->template('templates/shop/cms/block/_features.html.twig');
        self::assertStringContainsString('homepage|default(false)', $features);
        self::assertStringContainsString('cn-grid cn-grid--4 cn-grid--lined', $features);
        self::assertStringContainsString('cn-cms-features mb-5', $features);
    }

    public function testPromiseBarSupportsSemanticAndLegacyIcons(): void
    {
        $promise = $this->template('templates/shop/cms/block/_promise_bar.html.twig');
        self::assertStringContainsString("['shipping','advice','payment','projects']", $promise);
        self::assertStringContainsString("icon == '✓'", $promise);
        self::assertStringContainsString('_semantic_icon.html.twig', $promise);
    }

    private function template(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($contents);

        return $contents;
    }
}
