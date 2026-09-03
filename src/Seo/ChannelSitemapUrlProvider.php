<?php

declare(strict_types=1);

namespace App\Seo;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use App\Cms\CmsPagePublicationChecker;
use App\Repository\Cms\CmsPageRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ChannelSitemapUrlProvider
{
    public function __construct(private RepositoryInterface $productRepository, private UrlGeneratorInterface $router, private CmsPageRepository $cmsPages, private CmsPagePublicationChecker $cmsPublication)
    {
    }

    /** @return list<string> */
    public function urls(ChannelInterface $channel): array
    {
        if (!$channel->isEnabled() || trim((string) $channel->getHostname()) === '') {
            return [];
        }
        $locale = $channel->getDefaultLocale()?->getCode();
        if (!is_string($locale) || $locale === '') {
            return [];
        }
        $origin = 'https://' . $channel->getHostname();
        $urls = [$origin . $this->router->generate('sylius_shop_homepage', [], UrlGeneratorInterface::ABSOLUTE_PATH)];
        foreach ($this->cmsPages->sitemapPages($channel, $locale) as $page) {
            if ($this->cmsPublication->isVisible($page, $channel, $locale)) {
                $urls[] = $origin . '/' . $page->getTranslation($locale)?->getSlug();
            }
        }

        $root = $channel->getMenuTaxon();
        if ($root instanceof TaxonInterface) {
            foreach ($this->descendants($root) as $taxon) {
                $slug = $this->slug($taxon, $locale);
                if ($slug !== null) {
                    $urls[] = $origin . $this->router->generate('sylius_shop_product_index', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_PATH);
                }
            }
        }

        foreach ($this->productRepository->findBy(['enabled' => true]) as $product) {
            if (!$product instanceof ProductInterface || !$product->hasChannel($channel)) {
                continue;
            }
            $slug = $this->slug($product, $locale);
            if ($slug !== null) {
                $urls[] = $origin . $this->router->generate('sylius_shop_product_show', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_PATH);
            }
        }

        return array_values(array_unique($urls));
    }

    /** @return \Generator<int, TaxonInterface> */
    private function descendants(TaxonInterface $parent): \Generator
    {
        foreach ($parent->getChildren() as $child) {
            if (!$child instanceof TaxonInterface) {
                continue;
            }
            yield $child;
            yield from $this->descendants($child);
        }
    }

    private function slug(object $resource, string $locale): ?string
    {
        if (!method_exists($resource, 'getTranslations')) {
            return null;
        }
        foreach ($resource->getTranslations() as $translation) {
            if (is_object($translation) && method_exists($translation, 'getLocale') && method_exists($translation, 'getSlug') && $translation->getLocale() === $locale) {
                $slug = $translation->getSlug();

                return is_string($slug) && trim($slug) !== '' ? $slug : null;
            }
        }

        return null;
    }
}
