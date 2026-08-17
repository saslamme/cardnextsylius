<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use PHPUnit\Framework\TestCase;

final class ConfiguratorPageArchitectureTest extends TestCase
{
    public function testLandingPageIsNotAStandardPdp(): void
    {
        $page = (string) file_get_contents(__DIR__ . '/../../templates/shop/configurator/page.html.twig');

        self::assertStringContainsString('cn-configurator-page', $page);
        self::assertStringContainsString('cn-configurator-process', $page);
        self::assertStringContainsString('product.description|raw', $page);
        self::assertStringContainsString('rel="canonical"', $page);
        self::assertStringNotContainsString('cn-product-layout', $page);
        self::assertStringNotContainsString('add_to_cart', $page);
        self::assertStringNotContainsString('inventory', $page);
    }

    public function testCatchAllComesAfterExplicitStorefrontRoutes(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../config/routes/zz_cardnext_shop.yaml');

        self::assertStringContainsString('configuratorPath: .+', $routes);
        self::assertStringContainsString('priority: -1000', $routes);
        self::assertGreaterThan(strpos($routes, 'sylius_shop_product_show:'), strpos($routes, 'cardnext_shop_configurator_page:'));
    }
}
