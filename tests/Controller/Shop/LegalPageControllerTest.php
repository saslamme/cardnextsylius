<?php

declare(strict_types=1);

namespace App\Tests\Controller\Shop;

use App\Controller\Shop\LegalPageController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

final class LegalPageControllerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function legalPageRoutes(): iterable
    {
        yield 'imprint' => ['imprint', '/impressum', 'cardnext_shop_legal_imprint'];
        yield 'privacy' => ['privacy', '/datenschutz', 'cardnext_shop_legal_privacy'];
        yield 'terms' => ['terms', '/agb', 'cardnext_shop_legal_terms'];
    }

    #[DataProvider('legalPageRoutes')]
    public function testLegalPageRouteTakesPriorityOverGenericShopRoutes(
        string $method,
        string $path,
        string $name,
    ): void {
        $reflection = new \ReflectionMethod(LegalPageController::class, $method);
        $attributes = $reflection->getAttributes(Route::class);

        self::assertCount(1, $attributes);

        /** @var Route $route */
        $route = $attributes[0]->newInstance();

        self::assertSame($path, $route->path);
        self::assertSame($name, $route->name);
        self::assertSame(['GET'], $route->methods);
        self::assertSame(120, $route->priority);
    }
}
