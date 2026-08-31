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

        if ($resource instanceof TaxonInterface) {
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

        return $market->baseUrl() . $request->getBaseUrl() . $request->getPathInfo();
    }

    private function productIsAvailable(ProductInterface $product, MarketDefinition $target): bool
    {
        if (!$product->isEnabled()) {
            return false;
        }

        $channel = $this->channelRepository->findOneBy(['code' => $target->channelCode]);

        return $channel instanceof ChannelInterface && $channel->isEnabled() && $product->hasChannel($channel);
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
