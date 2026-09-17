<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Seo\SeoLandingPageFilterRedirectResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SeoLandingPageFilterRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(private SeoLandingPageFilterRedirectResolver $resolver) {}

    public static function getSubscribedEvents(): array
    {
        // RouterListener uses priority 32; route attributes must already be available.
        return [KernelEvents::REQUEST => ['redirectCuratedFilter', 0]];
    }

    public function redirectCuratedFilter(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('GET') || $request->attributes->get('_route') !== 'sylius_shop_product_index') {
            return;
        }
        $criteria = $request->query->all()['criteria'] ?? [];
        if (!is_array($criteria) || $criteria === []) {
            return;
        }

        $target = $this->resolver->resolve($request);
        if ($target !== null) $event->setResponse(new RedirectResponse($target, 301));
    }
}
