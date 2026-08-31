<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\International\MarketUrlResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class MarketSeoSubscriber implements EventSubscriberInterface
{
    public function __construct(private MarketUrlResolver $resolver)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['addMarketLinks', -10]];
    }

    public function addMarketLinks(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getResponse()->isSuccessful()) {
            return;
        }
        $response = $event->getResponse();
        if (!str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }
        $content = $response->getContent();
        if (!is_string($content) || !str_contains($content, '</head>')) {
            return;
        }

        $request = $event->getRequest();
        $tags = sprintf("\n<link rel=\"canonical\" href=\"%s\">", htmlspecialchars($this->resolver->canonical($request), \ENT_QUOTES));
        foreach ($this->resolver->links($request) as $link) {
            $tags .= sprintf(
                "\n<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\">",
                $link['market']->hreflang(),
                htmlspecialchars($link['url'], \ENT_QUOTES),
            );
        }

        // Consolidate older page-level canonicals into the host-aware source.
        $content = preg_replace('/<link\s+rel=["\']canonical["\'][^>]*>/i', '', $content) ?? $content;
        $response->setContent(str_replace('</head>', $tags . "\n</head>", $content));
    }
}
