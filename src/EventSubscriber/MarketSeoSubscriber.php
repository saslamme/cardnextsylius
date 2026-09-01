<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\International\CardnextMarketRegistry;
use App\International\MarketUrlResolver;
use App\Seo\ChannelCanonicalUrlResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class MarketSeoSubscriber implements EventSubscriberInterface
{
    public function __construct(private MarketUrlResolver $resolver, private CardnextMarketRegistry $markets, private ChannelCanonicalUrlResolver $canonicalResolver)
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
        $canonical = $this->canonicalResolver->resolve($request);
        if ($canonical === null) {
            return;
        }

        $tags = sprintf("\n<link rel=\"canonical\" href=\"%s\">", htmlspecialchars($canonical, \ENT_QUOTES));
        // Brand domains are intentionally not translations of Cardnext. The
        // registry membership of the resolved channel is the hreflang boundary.
        $market = $this->resolver->currentMarket();
        foreach ($market === null ? [] : $this->resolver->alternateLinks($request) as $link) {
            $tags .= sprintf(
                "\n<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\">",
                $link['market']->hreflang(),
                htmlspecialchars($link['url'], \ENT_QUOTES),
            );
        }

        // Consolidate older page-level SEO links into the host-aware source. This
        // deliberately matches link elements only; selector anchors are untouched.
        $content = preg_replace('/<link\s+rel=["\']canonical["\'][^>]*>/i', '', $content) ?? $content;
        $content = preg_replace('/<link\b(?=[^>]*\brel=["\']alternate["\'])(?=[^>]*\bhreflang=["\'][^"\']+["\'])[^>]*>/i', '', $content) ?? $content;
        $response->setContent(str_replace('</head>', $tags . "\n</head>", $content));
    }
}
