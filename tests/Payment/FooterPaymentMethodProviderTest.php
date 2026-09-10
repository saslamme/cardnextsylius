<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\Entity\Channel\Channel;
use App\Entity\Payment\GatewayConfig;
use App\Entity\Payment\PaymentMethod;
use App\Payment\FooterPaymentMethodProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\MolliePlugin\Entity\MollieGatewayConfig;
use Sylius\MolliePlugin\Repository\MollieGatewayConfigRepositoryInterface;

final class FooterPaymentMethodProviderTest extends TestCase
{
    #[Test]
    public function it_displays_a_regular_payment_method(): void
    {
        self::assertSame([['code' => 'invoice', 'label' => 'Rechnung']], $this->provide([$this->regular('invoice', 'Rechnung')]));
    }

    #[Test]
    public function it_ignores_a_disabled_payment_method(): void
    {
        self::assertSame([], $this->provide([]));
    }

    #[Test]
    public function it_ignores_a_payment_method_from_another_channel(): void
    {
        self::assertSame([], $this->provide([]));
    }

    #[Test]
    public function it_displays_enabled_mollie_paypal(): void
    {
        self::assertSame([['code' => 'paypal', 'label' => 'PayPal']], $this->provide([$this->mollieGateway()], [$this->mollieMethod('paypal')]));
    }

    #[Test]
    public function it_normalises_doctrine_mixed_results(): void
    {
        $mollieMethod = $this->mollieMethod('ideal');

        self::assertSame(
            [['code' => 'ideal', 'label' => 'iDEAL']],
            $this->provide([$this->mollieGateway()], [[
                0 => $mollieMethod,
                'minimumAmount' => null,
                'maximumAmount' => null,
            ]]),
        );
    }

    #[Test]
    public function it_ignores_a_mixed_result_without_a_mollie_method(): void
    {
        self::assertSame([], $this->provide([$this->mollieGateway()], [[
            'minimumAmount' => 100,
            'maximumAmount' => 1000,
        ]]));
    }

    #[Test]
    public function it_keeps_valid_methods_when_another_mixed_result_is_invalid(): void
    {
        self::assertSame(
            [['code' => 'paypal', 'label' => 'PayPal']],
            $this->provide([$this->mollieGateway()], [
                ['minimumAmount' => null],
                ['method' => $this->mollieMethod('paypal'), 'maximumAmount' => null],
            ]),
        );
    }

    #[Test]
    public function it_expands_creditcard_from_a_mixed_result(): void
    {
        self::assertSame([
            ['code' => 'visa', 'label' => 'Visa'],
            ['code' => 'mastercard', 'label' => 'Mastercard'],
        ], $this->provide([$this->mollieGateway()], [[
            'minimumAmount' => null,
            'method' => $this->mollieMethod('creditcard'),
            'maximumAmount' => null,
        ]]));
    }

    #[Test]
    public function it_displays_paypal_from_a_mixed_result(): void
    {
        self::assertSame(
            [['code' => 'paypal', 'label' => 'PayPal']],
            $this->provide([$this->mollieGateway()], [[
                'minimumAmount' => null,
                1 => $this->mollieMethod('paypal'),
                'maximumAmount' => null,
            ]]),
        );
    }

    #[Test]
    public function it_ignores_disabled_mollie_paypal(): void
    {
        self::assertSame([], $this->provide([$this->mollieGateway()], []));
    }

    #[Test]
    public function it_expands_mollie_creditcard_to_card_brands(): void
    {
        self::assertSame([
            ['code' => 'visa', 'label' => 'Visa'],
            ['code' => 'mastercard', 'label' => 'Mastercard'],
        ], $this->provide([$this->mollieGateway()], [$this->mollieMethod('creditcard')]));
    }

    #[Test]
    public function it_displays_all_enabled_mollie_methods_in_repository_order(): void
    {
        self::assertSame(['paypal', 'applepay', 'ideal', 'eps'], array_column($this->provide(
            [$this->mollieGateway()],
            [$this->mollieMethod('paypal'), $this->mollieMethod('applepay'), $this->mollieMethod('ideal'), $this->mollieMethod('eps')],
        ), 'code'));
    }

    #[Test]
    public function it_uses_the_mollie_name_for_an_unknown_future_method(): void
    {
        self::assertSame(
            [['code' => 'futurewallet', 'label' => 'Future Wallet']],
            $this->provide([$this->mollieGateway()], [$this->mollieMethod('future-wallet', 'Future Wallet')]),
        );
    }

    #[Test]
    public function it_deduplicates_regular_and_mollie_payment_methods(): void
    {
        self::assertSame(
            [['code' => 'paypal', 'label' => 'PayPal']],
            $this->provide([$this->regular('paypal', 'PayPal'), $this->mollieGateway()], [$this->mollieMethod('paypal')]),
        );
    }

    #[Test]
    public function it_does_not_query_a_mollie_gateway_from_another_channel(): void
    {
        self::assertSame([], $this->provide([], [], false));
    }

    #[Test]
    public function it_is_safe_without_payment_methods(): void
    {
        self::assertSame([], $this->provide([]));
    }

    /**
     * @param list<PaymentMethod> $paymentMethods
     * @param array<array-key, mixed> $mollieMethods
     *
     * @return list<array{code: string, label: string}>
     */
    private function provide(array $paymentMethods, array $mollieMethods = [], bool $expectMollieQuery = true): array
    {
        $channel = new Channel();
        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $paymentRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $paymentRepository->expects(self::once())->method('findEnabledForChannel')->with($channel)->willReturn($paymentMethods);

        $mollieRepository = $this->createMock(MollieGatewayConfigRepositoryInterface::class);
        $mollieGateways = array_filter($paymentMethods, static fn (PaymentMethod $method): bool => $method->getGatewayConfig()?->getFactoryName() === 'mollie');
        $expectedQueries = $expectMollieQuery ? count($mollieGateways) : 0;
        $mollieRepository->expects(self::exactly($expectedQueries))->method('findAllEnabledByGateway')->willReturn($mollieMethods);

        return (new FooterPaymentMethodProvider($channelContext, $paymentRepository, $mollieRepository))->provide();
    }

    private function regular(string $code, string $name): PaymentMethod
    {
        $method = new PaymentMethod();
        $method->setCode($code);
        $method->setCurrentLocale('de_DE');
        $method->setFallbackLocale('de_DE');
        $method->setName($name);

        return $method;
    }

    private function mollieGateway(): PaymentMethod
    {
        $gateway = new GatewayConfig();
        $gateway->setFactoryName('mollie');
        $method = new PaymentMethod();
        $method->setCode('mollie');
        $method->setGatewayConfig($gateway);

        return $method;
    }

    private function mollieMethod(string $methodId, ?string $name = null): MollieGatewayConfig
    {
        $method = new MollieGatewayConfig();
        $method->setMethodId($methodId);
        $method->setName($name);
        $method->enable();

        return $method;
    }
}
