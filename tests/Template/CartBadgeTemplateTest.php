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
    #[DataProvider('quantities')]
    public function testBadgeIsOnlyRenderedForNonEmptyCarts(int $quantity, bool $badgeExpected): void
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'));
        $twig->addFunction(new TwigFunction('sylius_test_html_attribute', static fn (): string => '', ['is_safe' => ['html']]));

        $html = $twig->render('shop/layout/header/cart.html.twig', [
            'attributes' => '',
            'cart' => (object) ['totalQuantity' => $quantity],
        ]);

        self::assertSame($badgeExpected, str_contains($html, 'cardnext-cart-badge'));
    }

    public static function quantities(): iterable
    {
        yield 'empty cart' => [0, false];
        yield 'cart with items' => [2, true];
    }
}
