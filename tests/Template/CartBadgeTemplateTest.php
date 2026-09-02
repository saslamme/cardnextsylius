<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class CartBadgeTemplateTest extends TestCase
{
    /**
     * @param list<object> $items
     * @param list<object> $configuredItems
     */
    #[DataProvider('cartPositions')]
    public function testBadgeCountsCartPositions(array $items, array $configuredItems, ?int $expectedCount): void
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addFilter(new TwigFilter('trans', static fn (string $message): string => $message));
        $twig->addFunction(new TwigFunction('sylius_test_html_attribute', static fn (): string => '', ['is_safe' => ['html']]));

        $html = $twig->render('shop/layout/header/cart.html.twig', [
            'attributes' => '',
            'cart' => (object) ['items' => $items, 'configuredItems' => $configuredItems],
        ]);

        if ($expectedCount === null) {
            self::assertStringNotContainsString('cardnext-cart-badge', $html);
            self::assertStringNotContainsString('cn-shop-header__count-badge', $html);

            return;
        }

        self::assertStringContainsString('cardnext-cart-badge', $html);
        self::assertStringContainsString('cardnext-cart-badge cn-shop-header__count-badge', $html);
        self::assertMatchesRegularExpression('/cn-shop-header__count-badge[^>]*>\s*' . $expectedCount . '\s*</', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);
    }

    /** @return iterable<string, array{list<object>, list<object>, int|null}> */
    public static function cartPositions(): iterable
    {
        yield 'empty cart' => [[], [], null];
        yield 'one regular item' => [[new \stdClass()], [], 1];
        yield 'one configured item' => [[], [new \stdClass()], 1];
        yield 'mixed cart' => [[new \stdClass()], [new \stdClass()], 2];
        yield 'four positions' => [[new \stdClass(), new \stdClass()], [new \stdClass(), new \stdClass()], 4];
    }
}
