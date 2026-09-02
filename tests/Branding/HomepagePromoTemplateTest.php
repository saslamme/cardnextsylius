<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use PHPUnit\Framework\TestCase;

final class HomepagePromoTemplateTest extends TestCase
{
    public function testPromoSectionIsEditorialAndConfigurationDriven(): void
    {
        $homepage = (string) file_get_contents(__DIR__ . '/../../templates/bundles/SyliusShopBundle/homepage/index.html.twig');
        $promo = (string) file_get_contents(__DIR__ . '/../../templates/shop/homepage/_promos.html.twig');

        self::assertGreaterThan(strpos($homepage, 'id="products"'), strpos($homepage, 'shop/homepage/_promos.html.twig'));
        self::assertStringContainsString('promo.enabled', $promo);
        self::assertStringContainsString('promo.imagePath', $promo);
        self::assertStringContainsString('promo.imageAlt', $promo);
        self::assertStringNotContainsString('Kartendrucker', $promo);
    }
}
