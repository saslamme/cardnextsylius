<?php

declare(strict_types=1);

namespace App\Tests\Maintenance;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class CartProtectionRoutingTest extends KernelTestCase
{
    public function testProtectionRoutesWinOverTheConfiguratorFallback(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $routes = $router->getRouteCollection();

        foreach ([
            'GET' => 'cardnext_shop_cart_protection_options',
            'POST' => 'cardnext_shop_cart_protection_update',
        ] as $method => $expectedRoute) {
            $context = new RequestContext();
            $context->setMethod($method);
            $match = (new UrlMatcher($routes, $context))->match('/cart/protection/149');

            self::assertSame($expectedRoute, $match['_route']);
            self::assertNotSame('cardnext_shop_configurator_page', $match['_route']);
        }
    }

    public function testCartNamespaceCannotMatchTheConfiguratorFallback(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $configuratorRoute = $router->getRouteCollection()->get('cardnext_shop_configurator_page');
        self::assertNotNull($configuratorRoute);

        $routes = new RouteCollection();
        $routes->add('cardnext_shop_configurator_page', $configuratorRoute);
        $matcher = new UrlMatcher($routes, new RequestContext());

        foreach ([
            '/admin',
            '/api/orders',
            '/angebot/request',
            '/downloads/file/1',
            '/leasing/request',
            '/cart',
            '/cart/protection/149',
            '/_profiler/token',
        ] as $path) {
            try {
                $match = $matcher->match($path);
                self::fail(sprintf('Reserved path "%s" matched route "%s".', $path, $match['_route']));
            } catch (ResourceNotFoundException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
