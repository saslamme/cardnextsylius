<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\Payment\FooterPaymentMethodPresentationResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FooterPaymentMethodPresentationResolverTest extends TestCase
{
    #[Test]
    #[DataProvider('knownPaymentMethods')]
    public function it_resolves_a_local_icon(string $code): void
    {
        $presentation = (new FooterPaymentMethodPresentationResolver())->resolve($code, 'Label');

        self::assertSame($code, $presentation['code']);
        self::assertSame('Label', $presentation['label']);
        self::assertSame(sprintf('payment-methods/%s.svg', $code), $presentation['icon']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function knownPaymentMethods(): iterable
    {
        yield 'Visa' => ['visa'];
        yield 'Mastercard' => ['mastercard'];
        yield 'PayPal' => ['paypal'];
        yield 'Apple Pay' => ['applepay'];
        yield 'Klarna' => ['klarna'];
        yield 'iDEAL' => ['ideal'];
        yield 'Bancontact' => ['bancontact'];
        yield 'invoice' => ['invoice'];
        yield 'prepayment' => ['prepayment'];
    }

    #[Test]
    public function it_preserves_an_unknown_method_as_a_text_fallback(): void
    {
        self::assertSame(
            ['code' => 'futurewallet', 'label' => 'Future Wallet', 'icon' => null],
            (new FooterPaymentMethodPresentationResolver())->resolve('futurewallet', 'Future Wallet'),
        );
    }
}
