<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

final class ConfiguratorPageArchitectureTest extends KernelTestCase
{
    public function testLandingPageIsNotAStandardPdp(): void
    {
        $page = (string) file_get_contents(__DIR__ . '/../../templates/shop/configurator/page.html.twig');

        self::assertStringContainsString('cn-configurator-page', $page);
        self::assertStringContainsString('cn-configurator-process', $page);
        self::assertStringContainsString('translation.description|raw', $page);
        self::assertStringNotContainsString('product.', $page);
        self::assertStringContainsString('rel="canonical"', $page);
        self::assertStringNotContainsString('cn-product-layout', $page);
        self::assertStringNotContainsString('add_to_cart', $page);
        self::assertStringNotContainsString('inventory', $page);
    }

    public function testCatchAllIsRegisteredAfterExplicitStorefrontRoutes(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $routeNames = array_keys($router->getRouteCollection()->all());

        self::assertContains('cardnext_shop_configurator_page', $routeNames);
        $catchAllPosition = array_search('cardnext_shop_configurator_page', $routeNames, true);
        self::assertIsInt($catchAllPosition);

        foreach (['sylius_shop_product_index', 'sylius_shop_product_show'] as $specificRoute) {
            $specificPosition = array_search($specificRoute, $routeNames, true);
            self::assertIsInt($specificPosition, sprintf('Route "%s" must be registered.', $specificRoute));
            self::assertGreaterThan($specificPosition, $catchAllPosition, sprintf('The catch-all must follow route "%s".', $specificRoute));
        }
    }

    public function testPublicUrlFunctionAndConfiguratorTemplateLoadInRealTwigEnvironment(): void
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        self::assertNotNull($twig->getFunction('cardnext_product_url'));
        self::assertSame(
            'shop/configurator/page.html.twig',
            $twig->load('shop/configurator/page.html.twig')->getTemplateName(),
        );
    }
}
