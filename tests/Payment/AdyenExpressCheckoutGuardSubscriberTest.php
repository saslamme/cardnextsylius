<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\EventSubscriber\AdyenExpressCheckoutGuardSubscriber;
use App\Payment\AdyenExpressCheckoutAvailabilityInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AdyenExpressCheckoutGuardSubscriberTest extends TestCase
{
    #[Test]
    public function it_returns_not_found_for_an_unavailable_express_checkout_endpoint(): void
    {
        $availability = $this->createStub(AdyenExpressCheckoutAvailabilityInterface::class);
        $availability->method('isAvailable')->willReturn(false);
        $subscriber = new AdyenExpressCheckoutGuardSubscriber($availability);

        $this->expectException(NotFoundHttpException::class);

        $subscriber->guardExpressCheckout($this->requestEvent('sylius_adyen_shop_express_checkout_cart_configuration'));
    }

    #[Test]
    public function it_preserves_express_checkout_endpoints_when_adyen_is_available(): void
    {
        $availability = $this->createStub(AdyenExpressCheckoutAvailabilityInterface::class);
        $availability->method('isAvailable')->willReturn(true);
        $subscriber = new AdyenExpressCheckoutGuardSubscriber($availability);

        $subscriber->guardExpressCheckout($this->requestEvent('sylius_adyen_shop_express_checkout_cart_configuration'));

        $this->addToAssertionCount(1);
    }

    private function requestEvent(string $route): RequestEvent
    {
        $request = new Request(attributes: ['_route' => $route]);

        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
