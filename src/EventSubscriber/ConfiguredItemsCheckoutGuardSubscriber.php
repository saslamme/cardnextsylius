<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Order\Order;
use Sylius\Component\Cart\Context\CartContextInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ConfiguredItemsCheckoutGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(private CartContextInterface $cartContext, private UrlGeneratorInterface $urls)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['guard', 20]];
    }

    public function guard(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !str_starts_with((string) $event->getRequest()->attributes->get('_route'), 'sylius_shop_checkout')) {
            return;
        }

        try {
            $cart = $this->cartContext->getCart();
        } catch (\Throwable) {
            return;
        }
        if (!$cart instanceof Order || !$cart->hasConfiguredItems()) {
            return;
        }
        $event->getRequest()->getSession()->getFlashBag()->add('error', 'Konfigurierte Artikel können derzeit noch nicht zur Kasse übergeben werden.');
        $event->setResponse(new RedirectResponse($this->urls->generate('sylius_shop_cart_summary')));
    }
}
