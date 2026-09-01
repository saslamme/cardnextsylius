<?php

declare(strict_types=1);

namespace App\Twig;

use App\International\MarketUrlResolver;
use App\Seo\ChannelCanonicalUrlResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MarketExtension extends AbstractExtension
{
    public function __construct(private readonly MarketUrlResolver $resolver, private readonly ChannelCanonicalUrlResolver $canonicalResolver, private readonly RequestStack $requests)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cardnext_current_market', $this->resolver->currentMarket(...)),
            new TwigFunction('cardnext_market_links', $this->links(...)),
            new TwigFunction('cardnext_canonical_url', $this->canonical(...)),
            new TwigFunction('cardnext_channel_asset_url', $this->assetUrl(...)),
        ];
    }

    /** @return list<array{market: \App\International\MarketDefinition, url: string}> */
    public function links(): array
    {
        $request = $this->requests->getCurrentRequest();

        return $request === null ? [] : $this->resolver->links($request);
    }

    public function canonical(): string
    {
        $request = $this->requests->getCurrentRequest();

        return $request === null ? '' : ($this->canonicalResolver->resolve($request) ?? '');
    }

    public function assetUrl(string $path): string
    {
        $request = $this->requests->getCurrentRequest();

        return $request === null ? '' : ($this->canonicalResolver->absoluteAsset($request, $path) ?? '');
    }
}
