<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class CmsDownloadRoutingTest extends KernelTestCase
{
    public function testDownloadRouteWinsOverTheConfiguratorFallback(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $match = $router->match('/downloads/file/1');

        self::assertSame('cardnext_cms_download_file', $match['_route']);
        self::assertNotSame('cardnext_shop_configurator_page', $match['_route']);
    }

    public function testLegitimateNestedPathStillUsesTheConfiguratorFallback(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $match = $router->match('/plastikkarten/plastikkarten-bedrucken');

        self::assertSame('cardnext_shop_configurator_page', $match['_route']);
        self::assertSame('plastikkarten/plastikkarten-bedrucken', $match['configuratorPath']);
    }

    public function testTechnicalNamespacesAreExcludedFromTheConfiguratorFallback(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $configuratorRoute = $router->getRouteCollection()->get('cardnext_shop_configurator_page');
        self::assertNotNull($configuratorRoute);

        $routes = new RouteCollection();
        $routes->add('cardnext_shop_configurator_page', $configuratorRoute);
        $matcher = new UrlMatcher($routes, new RequestContext());

        foreach (['/admin/anything', '/api/orders', '/angebot/request', '/downloads/file/1', '/_profiler/token'] as $path) {
            try {
                $match = $matcher->match($path);
                self::fail(sprintf('Reserved path "%s" matched route "%s".', $path, $match['_route']));
            } catch (ResourceNotFoundException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
