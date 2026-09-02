<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Payment\AdyenExpressCheckoutAvailabilityInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class AdyenExpressCheckoutGuardSubscriber implements EventSubscriberInterface
{
    private const ROUTE_PREFIX = 'sylius_adyen_shop_express_checkout_';

    public function __construct(private readonly AdyenExpressCheckoutAvailabilityInterface $availability)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['guardExpressCheckout', 0]];
    }

    public function guardExpressCheckout(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (!is_string($route) || !str_starts_with($route, self::ROUTE_PREFIX) || $this->availability->isAvailable()) {
            return;
        }

        throw new NotFoundHttpException('Adyen Express Checkout is not available for this channel.');
    }
}
