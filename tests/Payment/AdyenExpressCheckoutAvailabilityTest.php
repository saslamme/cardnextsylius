<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\Entity\Channel\Channel;
use App\Entity\Payment\GatewayConfig;
use App\Entity\Payment\PaymentMethod;
use App\Payment\AdyenExpressCheckoutAvailability;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;

final class AdyenExpressCheckoutAvailabilityTest extends TestCase
{
    #[Test]
    public function it_is_unavailable_without_any_adyen_payment_method(): void
    {
        self::assertFalse($this->availabilityFor([])->isAvailable());
    }

    #[Test]
    public function it_is_available_with_an_enabled_channel_assigned_adyen_payment_method(): void
    {
        self::assertTrue($this->availabilityFor([$this->paymentMethod('adyen')])->isAvailable());
    }

    #[Test]
    public function it_is_unavailable_when_adyen_is_not_assigned_to_the_current_channel(): void
    {
        // findEnabledForChannel excludes globally configured methods which are not assigned.
        self::assertFalse($this->availabilityFor([])->isAvailable());
    }

    #[Test]
    public function it_is_unavailable_when_the_adyen_payment_method_is_disabled(): void
    {
        // findEnabledForChannel excludes disabled methods.
        self::assertFalse($this->availabilityFor([])->isAvailable());
    }

    #[Test]
    public function it_ignores_enabled_non_adyen_payment_methods(): void
    {
        self::assertFalse($this->availabilityFor([$this->paymentMethod('offline')])->isAvailable());
    }

    /** @param list<PaymentMethod> $enabledPaymentMethods */
    private function availabilityFor(array $enabledPaymentMethods): AdyenExpressCheckoutAvailability
    {
        $channel = new Channel();
        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $repository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findEnabledForChannel')
            ->with($channel)
            ->willReturn($enabledPaymentMethods)
        ;

        return new AdyenExpressCheckoutAvailability($channelContext, $repository);
    }

    private function paymentMethod(string $factoryName): PaymentMethod
    {
        $gateway = new GatewayConfig();
        $gateway->setFactoryName($factoryName);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gateway);

        return $paymentMethod;
    }
}
