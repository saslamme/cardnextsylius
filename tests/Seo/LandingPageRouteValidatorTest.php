<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use App\Seo\LandingPageRouteValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class LandingPageRouteValidatorTest extends TestCase
{
    #[DataProvider('allowedPaths')]
    public function testMatchesPublicRoutesAsGetDuringAnAdminPostRequest(string $path): void
    {
        $context = new RequestContext(method: 'POST');
        $validator = new LandingPageRouteValidator($this->router($context));

        self::assertTrue($validator->isAllowed($path));
        self::assertSame('POST', $context->getMethod());
    }

    public static function allowedPaths(): iterable
    {
        yield ['/kartendrucker/retransfer'];
        yield ['/kartendrucker/zebra'];
    }

    #[DataProvider('reservedPaths')]
    public function testRejectsReservedApplicationPaths(string $path): void
    {
        $validator = new LandingPageRouteValidator($this->router(new RequestContext(method: 'POST')));

        self::assertFalse($validator->isAllowed($path));
    }

    public static function reservedPaths(): iterable
    {
        yield ['/admin/test'];
        yield ['/api/test'];
    }

    public function testTreatsResourceNotFoundAsValidationFailure(): void
    {
        $validator = new LandingPageRouteValidator($this->router(new RequestContext(method: 'POST')));

        self::assertFalse($validator->isAllowed('/not-found'));
    }

    public function testTreatsMethodNotAllowedAsValidationFailure(): void
    {
        $validator = new LandingPageRouteValidator($this->router(new RequestContext(method: 'POST')));

        self::assertFalse($validator->isAllowed('/post-only'));
    }

    private function router(RequestContext $context): RouterInterface
    {
        $routes = new RouteCollection();
        $routes->add('reserved_admin', new Route('/admin/{path}', requirements: ['path' => '.+']));
        $routes->add('reserved_api', new Route('/api/{path}', requirements: ['path' => '.+']));
        $routes->add('post_only', new Route('/post-only', methods: ['POST']));
        $routes->add('cardnext_shop_configurator_page', new Route('/{path}', requirements: ['path' => '(?!post-only$|not-found$).+'], methods: ['GET']));

        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn($context);
        $router->method('getRouteCollection')->willReturn($routes);

        return $router;
    }
}
