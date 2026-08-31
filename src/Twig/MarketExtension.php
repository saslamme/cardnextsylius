<?php

declare(strict_types=1);

namespace App\Twig;

use App\International\MarketUrlResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MarketExtension extends AbstractExtension
{
    public function __construct(private readonly MarketUrlResolver $resolver, private readonly RequestStack $requests)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cardnext_current_market', $this->resolver->currentMarket(...)),
            new TwigFunction('cardnext_market_links', $this->links(...)),
            new TwigFunction('cardnext_canonical_url', $this->canonical(...)),
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

        return $request === null ? '' : $this->resolver->canonical($request);
    }
}
