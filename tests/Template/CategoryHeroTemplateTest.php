<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class CategoryHeroTemplateTest extends TestCase
{
    public function testHeroRendersCategoryContentWithoutAParentKicker(): void
    {
        $hero = (string) file_get_contents(__DIR__ . '/../../templates/shop/category/hero.html.twig');

        self::assertStringContainsString('<h1 class="cn-title cn-title--page">{{ taxon.name }}</h1>', $hero);
        self::assertStringContainsString('{% if taxon.description %}', $hero);
        self::assertStringContainsString('<div class="cn-page-hero__description">{{ taxon.description|raw }}</div>', $hero);
        self::assertStringNotContainsString('taxon.parent.name', $hero);
        self::assertStringNotContainsString('cn-kicker', $hero);
    }
}
