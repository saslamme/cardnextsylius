<?php

declare(strict_types=1);

namespace App\International;

use App\Entity\Product\Product;
use App\Entity\Taxonomy\Taxon;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
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

    /**
     * @param ProductRepositoryInterface<Product> $productRepository
     * @param TaxonRepositoryInterface<Taxon> $taxonRepository
     */
    public function __construct(
        private CardnextMarketRegistry $markets,
        private ChannelContextInterface $channelContext,
        private RepositoryInterface $channelRepository,
        private UrlGeneratorInterface $router,
        private ProductRepositoryInterface $productRepository,
        private TaxonRepositoryInterface $taxonRepository,
        private RepositoryInterface $productTranslationRepository,
        private RepositoryInterface $taxonTranslationRepository,
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
        $product = $this->currentProduct($request);
        $taxon = $product instanceof ProductInterface ? null : $this->currentTaxon($request);
        $channels = ($product instanceof ProductInterface || $taxon instanceof TaxonInterface) ? $this->enabledChannels($request) : [];
        $links = [];

        foreach ($this->markets->all() as $market) {
            if (!$market->enabled) {
                continue;
            }

            $url = null;
            $channel = $channels[$market->channelCode] ?? null;
            if ($product instanceof ProductInterface && $channel instanceof ChannelInterface && $this->productIsAvailableInChannel($product, $channel)) {
                $slug = $this->translatedSlugs($request, $product)[$market->localeCode] ?? null;
                if ($slug !== null) {
                    $url = $this->absolute($market, 'sylius_shop_product_show', ['_locale' => $market->localeCode, 'slug' => $slug]);
                }
            } elseif ($taxon instanceof TaxonInterface && $channel instanceof ChannelInterface && $this->taxonIsAvailableInChannel($taxon, $channel)) {
                $slug = $this->translatedSlugs($request, $taxon)[$market->localeCode] ?? null;
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
        $resource = $this->currentProduct($request) ?? $this->currentTaxon($request);
        $parameters = ['_locale' => $target->localeCode];

        $targetChannel = $this->enabledChannels($request)[$target->channelCode] ?? null;
        if ($resource instanceof ProductInterface && $targetChannel instanceof ChannelInterface && $this->productIsAvailableInChannel($resource, $targetChannel)) {
            $slug = $this->translatedSlugs($request, $resource)[$target->localeCode] ?? null;
            if ($slug !== null) {
                return $this->absolute($target, 'sylius_shop_product_show', $parameters + ['slug' => $slug]);
            }
        }

        if ($resource instanceof TaxonInterface && $targetChannel instanceof ChannelInterface && $this->taxonIsAvailableInChannel($resource, $targetChannel)) {
            $slug = $this->translatedSlugs($request, $resource)[$target->localeCode] ?? null;
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

        $product = $this->currentProduct($request);
        if ($product instanceof ProductInterface) {
            $slug = $this->translatedSlugs($request, $product)[$market->localeCode] ?? null;
            if ($slug !== null) {
                return $this->absolute($market, 'sylius_shop_product_show', ['_locale' => $market->localeCode, 'slug' => $slug]);
            }
        }

        $taxon = $this->currentTaxon($request);
        if ($taxon instanceof TaxonInterface) {
            $slug = $this->translatedSlugs($request, $taxon)[$market->localeCode] ?? null;
            if ($slug !== null) {
                return $this->absolute($market, 'sylius_shop_product_index', ['_locale' => $market->localeCode, 'slug' => $slug]);
            }
        }

        return $market->baseUrl() . $request->getBaseUrl() . $request->getPathInfo();
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
    private function enabledChannels(Request $request): array
    {
        $cached = $request->attributes->get('_cardnext_market_channels');
        if (is_array($cached)) {
            $channels = [];
            foreach ($cached as $code => $channel) {
                if (is_string($code) && $channel instanceof ChannelInterface) {
                    $channels[$code] = $channel;
                }
            }

            return $channels;
        }

        $codes = array_map(static fn (MarketDefinition $market): string => $market->channelCode, $this->markets->all());
        $channels = [];
        foreach ($this->channelRepository->findBy(['code' => $codes]) as $channel) {
            if ($channel instanceof ChannelInterface && $channel->isEnabled() && is_string($channel->getCode())) {
                $channels[$channel->getCode()] = $channel;
            }
        }

        $request->attributes->set('_cardnext_market_channels', $channels);

        return $channels;
    }

    private function currentProduct(Request $request): ?ProductInterface
    {
        $product = $request->attributes->get('cardnext_product');
        if ($product instanceof ProductInterface) {
            return $product;
        }

        if ($request->attributes->get('_cardnext_market_product_resolved') === true) {
            return null;
        }
        $request->attributes->set('_cardnext_market_product_resolved', true);

        $route = $request->attributes->get('_route');
        if ($route !== 'sylius_shop_product_show') {
            return null;
        }

        $locale = $this->routeString($request, '_locale');
        $slug = $this->routeString($request, 'slug');
        if ($locale === null || $slug === null) {
            return null;
        }

        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            return null;
        }

        $product = $this->productRepository->findOneByChannelAndSlug($channel, $locale, $slug);
        if (!$product instanceof ProductInterface) {
            return null;
        }

        $request->attributes->set('cardnext_product', $product);

        return $product;
    }

    private function currentTaxon(Request $request): ?TaxonInterface
    {
        $taxon = $request->attributes->get('cardnext_taxon');
        if ($taxon instanceof TaxonInterface) {
            return $taxon;
        }

        if ($request->attributes->get('_cardnext_market_taxon_resolved') === true) {
            return null;
        }
        $request->attributes->set('_cardnext_market_taxon_resolved', true);

        if ($request->attributes->get('_route') !== 'sylius_shop_product_index') {
            return null;
        }

        $locale = $this->routeString($request, '_locale');
        $slug = $this->routeString($request, 'slug');
        if ($locale === null || $slug === null) {
            return null;
        }

        $channel = $this->channelContext->getChannel();
        $taxon = $this->taxonRepository->findOneBySlug($slug, $locale);
        if (!$channel instanceof ChannelInterface || !$taxon instanceof TaxonInterface || !$this->taxonIsAvailableInChannel($taxon, $channel)) {
            return null;
        }

        $request->attributes->set('cardnext_taxon', $taxon);

        return $taxon;
    }

    private function routeString(Request $request, string $name): ?string
    {
        $value = $request->attributes->get($name);
        if (!is_string($value) || trim($value) === '') {
            $parameters = $request->attributes->get('_route_params', []);
            $value = is_array($parameters) ? ($parameters[$name] ?? null) : null;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
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

    /** @return array<string, string> */
    private function translatedSlugs(Request $request, object $resource): array
    {
        $cacheKey = $resource instanceof ProductInterface ? '_cardnext_product_translation_slugs' : '_cardnext_taxon_translation_slugs';
        $cached = $request->attributes->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $repository = $resource instanceof ProductInterface ? $this->productTranslationRepository : $this->taxonTranslationRepository;
        $locales = array_values(array_map(
            static fn (MarketDefinition $market): string => $market->localeCode,
            array_filter($this->markets->all(), static fn (MarketDefinition $market): bool => $market->enabled),
        ));
        $slugs = [];
        // An explicit translation query is required here: Sylius' product slug
        // lookup leaves getTranslations() initialized with only the current locale.
        foreach ($repository->findBy(['translatable' => $resource, 'locale' => $locales]) as $translation) {
            if (is_object($translation) && method_exists($translation, 'getLocale') && method_exists($translation, 'getSlug')) {
                $locale = $translation->getLocale();
                $slug = $translation->getSlug();
                if (is_string($locale) && is_string($slug) && trim($slug) !== '') {
                    $slugs[$locale] = $slug;
                }
            }
        }

        $request->attributes->set($cacheKey, $slugs);

        return $slugs;
    }

    /** @param array<string, string> $parameters */
    private function absolute(MarketDefinition $market, string $route, array $parameters): string
    {
        $path = $this->router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);

        return $market->baseUrl() . $path;
    }
}
