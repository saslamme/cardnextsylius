<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
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

    public function testHomepageContentAdminRoutesWinOverTheConfiguratorCatchAll(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        self::assertSame('cardnext_admin_homepage_content_index', $router->match('/admin/cardnext/homepage-content')['_route']);
        self::assertSame('cardnext_admin_homepage_content_create', $router->match('/admin/cardnext/homepage-content/new')['_route']);
        self::assertSame('cardnext_admin_homepage_content_edit', $router->match('/admin/cardnext/homepage-content/1/edit')['_route']);
    }

    public function testReservedApplicationPathsCannotMatchTheConfiguratorRoute(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $configuratorRoute = $router->getRouteCollection()->get('cardnext_shop_configurator_page');
        self::assertNotNull($configuratorRoute);

        $routes = new RouteCollection();
        $routes->add('cardnext_shop_configurator_page', $configuratorRoute);
        $matcher = new UrlMatcher($routes, new RequestContext());

        foreach (['/admin', '/admin/anything', '/api', '/api/orders', '/_media/cache/image.jpg', '/_profiler', '/_wdt/token', '/_fragment'] as $path) {
            try {
                $match = $matcher->match($path);
                self::fail(sprintf('Reserved path "%s" matched route "%s".', $path, $match['_route']));
            } catch (ResourceNotFoundException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testLegitimateNestedConfiguratorPathStillMatchesTheConfiguratorRoute(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $configuratorRoute = $router->getRouteCollection()->get('cardnext_shop_configurator_page');
        self::assertNotNull($configuratorRoute);

        $routes = new RouteCollection();
        $routes->add('cardnext_shop_configurator_page', $configuratorRoute);
        $matcher = new UrlMatcher($routes, new RequestContext());

        $match = $matcher->match('/plastikkarten/plastikkarten-bedrucken');

        self::assertSame('cardnext_shop_configurator_page', $match['_route']);
        self::assertSame('plastikkarten/plastikkarten-bedrucken', $match['configuratorPath']);
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
