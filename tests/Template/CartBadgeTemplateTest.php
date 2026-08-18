<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class CartBadgeTemplateTest extends TestCase
{
    #[DataProvider('cartPositions')]
    public function testBadgeCountsCartPositions(array $items, array $configuredItems, ?int $expectedCount): void
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'));
        $twig->addFunction(new TwigFunction('sylius_test_html_attribute', static fn (): string => '', ['is_safe' => ['html']]));

        $html = $twig->render('shop/layout/header/cart.html.twig', [
            'attributes' => '',
            'cart' => (object) ['items' => $items, 'configuredItems' => $configuredItems],
        ]);

        if ($expectedCount === null) {
            self::assertStringNotContainsString('cardnext-cart-badge', $html);

            return;
        }

        self::assertStringContainsString('cardnext-cart-badge', $html);
        self::assertMatchesRegularExpression('/cardnext-cart-badge[^>]*>\s*'.$expectedCount.'\s*</', $html);
    }

    public static function cartPositions(): iterable
    {
        yield 'empty cart' => [[], [], null];
        yield 'one regular item' => [[new \stdClass()], [], 1];
        yield 'one configured item' => [[], [new \stdClass()], 1];
        yield 'mixed cart' => [[new \stdClass()], [new \stdClass()], 2];
        yield 'four positions' => [[new \stdClass(), new \stdClass()], [new \stdClass(), new \stdClass()], 4];
    }
}
