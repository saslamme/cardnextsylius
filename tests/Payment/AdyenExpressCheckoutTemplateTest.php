<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

final class AdyenExpressCheckoutTemplateTest extends TestCase
{
    #[Test]
    public function it_does_not_render_or_initialize_adyen_when_unavailable(): void
    {
        $twig = $this->twig(false);

        $rendered = $twig->render('shop/cart/_adyen_express_checkout.html.twig');

        self::assertStringNotContainsString('adyen-cart-express-checkout', $rendered);
        self::assertStringNotContainsString('cart-configuration', $rendered);
    }

    #[Test]
    public function it_keeps_adyen_express_checkout_available_when_configured(): void
    {
        $twig = $this->twig(true);

        $rendered = $twig->render('shop/cart/_adyen_express_checkout.html.twig');

        self::assertStringContainsString('adyen-cart-express-checkout', $rendered);
        self::assertStringContainsString('/adyen/express-checkout/cart-configuration', $rendered);
    }

    private function twig(bool $available): Environment
    {
        $template = file_get_contents(__DIR__ . '/../../templates/shop/cart/_adyen_express_checkout.html.twig');
        self::assertIsString($template);

        $loader = new ArrayLoader([
            'shop/cart/_adyen_express_checkout.html.twig' => $template,
            '@SyliusAdyenPlugin/shop/cart/index/content/form/sections/general/express_checkout.html.twig' => '<div id="adyen-cart-express-checkout" data-config-url="/adyen/express-checkout/cart-configuration"></div>',
        ]);
        $twig = new Environment($loader);
        $twig->addFunction(new TwigFunction(
            'cardnext_adyen_express_checkout_available',
            static fn (): bool => $available,
        ));
        $twig->addFunction(new TwigFunction(
            'path',
            static fn (): string => '/adyen/express-checkout/cart-configuration',
        ));

        return $twig;
    }
}
