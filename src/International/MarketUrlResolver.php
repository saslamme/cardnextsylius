<?php

declare(strict_types=1);

namespace App\International;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class MarketUrlResolver
{
    private const SEO_LOCALE_ONLY_ROUTES = [
        'sylius_shop_homepage',
        'cardnext_shop_search',
        'cardnext_shop_brands',
        'cardnext_shop_consumable_finder',
        'cardnext_shop_legal_imprint',
        'cardnext_shop_legal_privacy',
        'cardnext_shop_legal_terms',
    ];

    public function __construct(
        private CardnextMarketRegistry $markets,
        private ChannelContextInterface $channelContext,
        private RepositoryInterface $channelRepository,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function currentMarket(): ?MarketDefinition
    {
        return $this->markets->get((string) $this->channelContext->getChannel()->getCode());
    }

    /** @return list<array{market: MarketDefinition, url: string}> */
    public function links(Request $request): array
    {
        $links = [];
        foreach ($this->markets->all() as $market) {
            if ($market->enabled) {
                $links[] = ['market' => $market, 'url' => $this->switchUrl($request, $market)];
            }
        }

        return $links;
    }

    /** @return list<array{market: MarketDefinition, url: string}> */
    public function alternateLinks(Request $request): array
    {
        $product = $request->attributes->get('cardnext_product');
        $taxon = $request->attributes->get('cardnext_taxon');
        $channels = ($product instanceof ProductInterface || $taxon instanceof TaxonInterface) ? $this->enabledChannels() : [];
        $links = [];

        foreach ($this->markets->all() as $market) {
            if (!$market->enabled) {
                continue;
            }

            $url = null;
            $channel = $channels[$market->channelCode] ?? null;
            if ($product instanceof ProductInterface && $channel instanceof ChannelInterface && $this->productIsAvailableInChannel($product, $channel)) {
                $slug = $this->translatedSlug($product, $market->localeCode);
                if ($slug !== null) {
                    $url = $this->absolute($market, 'sylius_shop_product_show', ['_locale' => $market->localeCode, 'slug' => $slug]);
                }
            } elseif ($taxon instanceof TaxonInterface && $channel instanceof ChannelInterface && $this->taxonIsAvailableInChannel($taxon, $channel)) {
                $slug = $this->translatedSlug($taxon, $market->localeCode);
                if ($slug !== null) {
                    $url = $this->absolute($market, 'sylius_shop_product_index', ['_locale' => $market->localeCode, 'slug' => $slug]);
                }
            } elseif (!$product instanceof ProductInterface && !$taxon instanceof TaxonInterface) {
                $url = $this->localeOnlySeoUrl($request, $market);
            }

            if ($url !== null) {
                $links[] = ['market' => $market, 'url' => $url];
            }
        }

        return $links;
    }

    public function switchUrl(Request $request, MarketDefinition $target): string
    {
        $routeAttribute = $request->attributes->get('_route');
        $route = is_string($routeAttribute) ? $routeAttribute : '';
        $resource = $request->attributes->get('cardnext_product') ?? $request->attributes->get('cardnext_taxon');
        $parameters = ['_locale' => $target->localeCode];

        if ($resource instanceof ProductInterface && $this->productIsAvailable($resource, $target)) {
            $slug = $this->translatedSlug($resource, $target->localeCode);
            if ($slug !== null) {
                return $this->absolute($target, 'sylius_shop_product_show', $parameters + ['slug' => $slug]);
            }
        }

        if ($resource instanceof TaxonInterface && $this->taxonIsAvailable($resource, $target)) {
            $slug = $this->translatedSlug($resource, $target->localeCode);
            if ($slug !== null) {
                return $this->absolute($target, 'sylius_shop_product_index', $parameters + ['slug' => $slug]);
            }
        }

        // Only preserve routes whose parameters are locale-only. Unknown and
        // channel-dependent resources deliberately fall back to the homepage.
        $routeParameters = $request->attributes->get('_route_params', []);
        if ($route !== '' && is_array($routeParameters) && array_diff_key($routeParameters, ['_locale' => true]) === []) {
            try {
                return $this->absolute($target, $route, $parameters);
            } catch (\Throwable) {
                // A non-portable route must never turn the market selector into a 404.
            }
        }

        return $this->absolute($target, 'sylius_shop_homepage', $parameters);
    }

    public function canonical(Request $request): string
    {
        $market = $this->currentMarket();
        if ($market === null) {
            return $request->getUri();
        }

        $product = $request->attributes->get('cardnext_product');
        if ($product instanceof ProductInterface) {
            $slug = $this->translatedSlug($product, $market->localeCode);
            if ($slug !== null) {
                return $this->absolute($market, 'sylius_shop_product_show', ['_locale' => $market->localeCode, 'slug' => $slug]);
            }
        }

        $taxon = $request->attributes->get('cardnext_taxon');
        if ($taxon instanceof TaxonInterface) {
            $slug = $this->translatedSlug($taxon, $market->localeCode);
            if ($slug !== null) {
                return $this->absolute($market, 'sylius_shop_product_index', ['_locale' => $market->localeCode, 'slug' => $slug]);
            }
        }

        return $market->baseUrl() . $request->getBaseUrl() . $request->getPathInfo();
    }

    private function productIsAvailable(ProductInterface $product, MarketDefinition $target): bool
    {
        if (!$product->isEnabled()) {
            return false;
        }

        $channel = $this->channelRepository->findOneBy(['code' => $target->channelCode]);

        return $channel instanceof ChannelInterface && $this->productIsAvailableInChannel($product, $channel);
    }

    private function taxonIsAvailable(TaxonInterface $taxon, MarketDefinition $target): bool
    {
        $channel = $this->channelRepository->findOneBy(['code' => $target->channelCode]);

        return $channel instanceof ChannelInterface && $this->taxonIsAvailableInChannel($taxon, $channel);
    }

    private function productIsAvailableInChannel(ProductInterface $product, ChannelInterface $channel): bool
    {
        return $product->isEnabled() && $channel->isEnabled() && $product->hasChannel($channel);
    }

    private function taxonIsAvailableInChannel(TaxonInterface $taxon, ChannelInterface $channel): bool
    {
        if (!$channel->isEnabled()) {
            return false;
        }

        $menuTaxon = $channel->getMenuTaxon();
        $root = $taxon->getRoot();

        return $menuTaxon instanceof TaxonInterface && $root instanceof TaxonInterface && $menuTaxon->getCode() === $root->getCode();
    }

    /** @return array<string, ChannelInterface> */
    private function enabledChannels(): array
    {
        $codes = array_map(static fn (MarketDefinition $market): string => $market->channelCode, $this->markets->all());
        $channels = [];
        foreach ($this->channelRepository->findBy(['code' => $codes]) as $channel) {
            if ($channel instanceof ChannelInterface && $channel->isEnabled() && is_string($channel->getCode())) {
                $channels[$channel->getCode()] = $channel;
            }
        }

        return $channels;
    }

    private function localeOnlySeoUrl(Request $request, MarketDefinition $market): ?string
    {
        $route = $request->attributes->get('_route');
        $routeParameters = $request->attributes->get('_route_params', []);
        if (!is_string($route) || !in_array($route, self::SEO_LOCALE_ONLY_ROUTES, true) || !is_array($routeParameters) || array_diff_key($routeParameters, ['_locale' => true]) !== []) {
            return null;
        }

        try {
            return $this->absolute($market, $route, ['_locale' => $market->localeCode]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function translatedSlug(object $resource, string $locale): ?string
    {
        if (!method_exists($resource, 'getTranslations')) {
            return null;
        }
        foreach ($resource->getTranslations() as $translation) {
            if (is_object($translation) && method_exists($translation, 'getLocale') && $translation->getLocale() === $locale && method_exists($translation, 'getSlug')) {
                $slug = $translation->getSlug();

                return is_string($slug) && trim($slug) !== '' ? $slug : null;
            }
        }

        return null;
    }

    /** @param array<string, string> $parameters */
    private function absolute(MarketDefinition $market, string $route, array $parameters): string
    {
        $path = $this->router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);

        return $market->baseUrl() . $path;
    }
}
