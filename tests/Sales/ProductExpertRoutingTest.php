<?php

declare(strict_types=1);

namespace App\Tests\Sales;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class ProductExpertRoutingTest extends KernelTestCase
{
    public function testProductExpertPageUsesTheExplicitStorefrontRoute(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        self::assertSame(
            'cardnext_shop_product_expert_show',
            $router->match('/experte/sascha-lammers')['_route'],
        );
    }

    public function testProductExpertNamespaceCannotMatchTheConfiguratorCatchAll(): void
    {
        $matcher = $this->createConfiguratorMatcher();

        foreach (['/experte', '/experte/foo'] as $path) {
            try {
                $match = $matcher->match($path);
                self::fail(sprintf('Product expert path "%s" matched route "%s".', $path, $match['_route']));
            } catch (ResourceNotFoundException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** @dataProvider provideRegularCatchAllPaths */
    public function testRegularCatchAllPathsContinueToMatch(string $path): void
    {
        $match = $this->createConfiguratorMatcher()->match($path);

        self::assertSame('cardnext_shop_configurator_page', $match['_route']);
        self::assertSame(ltrim($path, '/'), $match['configuratorPath']);
    }

    public static function provideRegularCatchAllPaths(): iterable
    {
        yield 'configurator' => ['/kartendrucker/retransfer'];
        yield 'CMS page' => ['/ein-beliebiger-cms-pfad'];
    }

    private function createConfiguratorMatcher(): UrlMatcher
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $configuratorRoute = $router->getRouteCollection()->get('cardnext_shop_configurator_page');
        self::assertNotNull($configuratorRoute);

        $routes = new RouteCollection();
        $routes->add('cardnext_shop_configurator_page', $configuratorRoute);

        return new UrlMatcher($routes, new RequestContext());
    }
}
