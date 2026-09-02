<?php

declare(strict_types=1);

namespace App\Seo\StructuredData;

use App\Branding\ChannelBrandingResolver;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Seo\ChannelCanonicalUrlResolver;
use App\Service\B2BPriceResolver;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class StructuredDataBuilder
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private ChannelBrandingResolver $brandingResolver,
        private ChannelCanonicalUrlResolver $canonicalUrlResolver,
        private B2BPriceResolver $priceResolver,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function homepage(Request $request): ?array
    {
        $channel = $this->channel();
        $url = $this->canonicalUrlResolver->resolve($request);
        if ($channel === null || $url === null) {
            return null;
        }

        return ['@context' => 'https://schema.org', '@graph' => [
            $this->organization($request, $channel, $url),
            ['@type' => 'WebSite', '@id' => $this->origin($url) . '/#website', 'url' => $url, 'name' => $this->brandingResolver->resolveChannel($channel)->brandName, 'publisher' => ['@id' => $this->organizationId($url)]],
        ]];
    }

    /** @return array<string, mixed>|null */
    public function product(Request $request, Product $product): ?array
    {
        $channel = $this->channel();
        $canonical = $this->canonicalUrlResolver->resolve($request);
        if ($channel === null || $canonical === null || !$product->isEnabled() || !$product->hasChannel($channel)) {
            return null;
        }

        $variant = null;
        foreach ($product->getEnabledVariants() as $candidate) {
            if ($candidate instanceof ProductVariant) {
                $variant = $candidate;
                break;
            }
        }
        if ($variant === null) {
            return null;
        }

        $data = ['@type' => 'Product', '@id' => $canonical . '#product', 'url' => $canonical, 'name' => (string) $product->getName(), 'sku' => (string) $variant->getCode()];
        $description = trim(strip_tags((string) ($product->getShortDescription() ?: $product->getDescription())));
        if ($description !== '') {
            $data['description'] = mb_substr(preg_replace('/\s+/u', ' ', $description) ?? $description, 0, 5000);
        }
        $images = [];
        foreach ($product->getImages() as $image) {
            if (($path = $image->getPath()) !== null && ($absolute = $this->canonicalUrlResolver->absoluteAsset($request, $path)) !== null) {
                $images[] = $absolute;
            }
        }
        if ($images !== []) {
            $data['image'] = array_values(array_unique($images));
        }
        if (($manufacturer = $product->getManufacturer()) !== null && $manufacturer->getName() !== '') {
            $data['brand'] = ['@type' => 'Brand', 'name' => $manufacturer->getName()];
        }
        if ($product->getModel() !== null) {
            $data['model'] = $product->getModel();
        }
        if ($variant->getManufacturerPartNumber() !== null) {
            $data['mpn'] = $variant->getManufacturerPartNumber();
        }
        $gtin = $variant->getGtin();
        if ($gtin !== null && preg_match('/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/D', $gtin)) {
            $data['gtin' . strlen($gtin)] = $gtin;
        }

        // The storefront price calculator resolves its headline price at quantity one.
        // Supplying no customer deliberately selects only the anonymous/public rules.
        $minorPrice = $this->priceResolver->resolve($variant, $channel, 1, null)
            ?? $variant->getChannelPricingForChannel($channel)?->getPrice();
        if ($minorPrice !== null && ($currency = $channel->getBaseCurrency()) !== null) {
            $data['offers'] = ['@type' => 'Offer', 'url' => $canonical, 'priceCurrency' => $currency->getCode(), 'price' => number_format($minorPrice / 100, 2, '.', ''), 'availability' => $variant->isTracked() && ($variant->getOnHand() - $variant->getOnHold()) <= 0 ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock', 'itemCondition' => 'https://schema.org/NewCondition', 'seller' => ['@id' => $this->organizationId($canonical)]];
        }

        $graph = [$this->organization($request, $channel, $this->homepageUrl($request)), $this->breadcrumbs($request, $product->getMainTaxon(), (string) $product->getName(), $canonical), $data];

        return ['@context' => 'https://schema.org', '@graph' => array_values(array_filter($graph))];
    }

    /** @return array<string, mixed>|null */
    public function taxon(Request $request, TaxonInterface $taxon): ?array
    {
        $url = $this->canonicalUrlResolver->resolve($request);
        if ($url === null) {
            return null;
        }

        return ['@context' => 'https://schema.org', '@graph' => [$this->breadcrumbs($request, $taxon, (string) $taxon->getName(), $url)]];
    }

    /** @return array<string, mixed> */
    private function organization(Request $request, ChannelInterface $channel, string $homepageUrl): array
    {
        $branding = $this->brandingResolver->resolveChannel($channel);
        $organization = ['@type' => 'Organization', '@id' => $this->organizationId($homepageUrl), 'name' => $branding->brandName, 'url' => $homepageUrl];
        if (($logo = $this->canonicalUrlResolver->absoluteAsset($request, $branding->logoPath)) !== null) {
            $organization['logo'] = $logo;
        }

        return $organization;
    }

    /** @return array<string, mixed> */
    private function breadcrumbs(Request $request, ?TaxonInterface $taxon, string $pageName, string $pageUrl): array
    {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $this->homepageUrl($request)]];
        $chain = [];
        for ($current = $taxon; $current !== null; $current = $current->getParent()) {
            if ($current->getParent() !== null) {
                array_unshift($chain, $current);
            }
        }
        foreach ($chain as $node) {
            $path = $this->urlGenerator->generate('sylius_shop_product_index', ['slug' => $node->getSlug()]);
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => (string) $node->getName(), 'item' => $this->canonicalUrlResolver->absoluteAsset($request, $path)];
        }
        if ($items[count($items) - 1]['item'] !== $pageUrl) {
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $pageName, 'item' => $pageUrl];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    private function homepageUrl(Request $request): string
    {
        $path = $this->urlGenerator->generate('sylius_shop_homepage');

        return $this->canonicalUrlResolver->absoluteAsset($request, $path) ?? '';
    }

    private function organizationId(string $url): string { return $this->origin($url) . '/#organization'; }
    private function origin(string $url): string { return (string) parse_url($url, \PHP_URL_SCHEME) . '://' . (string) parse_url($url, \PHP_URL_HOST); }
    private function channel(): ?ChannelInterface
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (\Throwable) {
            return null;
        }

        return $channel instanceof ChannelInterface && $channel->isEnabled() ? $channel : null;
    }
}
