<?php

declare(strict_types=1);

namespace App\Seo;

use App\Entity\Channel\Channel;
use App\Entity\Seo\SeoLandingPage;
use App\Entity\Taxonomy\Taxon;
use App\Grid\Filter\FilterDataNormalizer;
use App\Repository\Seo\SeoLandingPageRepository;
use App\Service\ProductFacetDefinitionService;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class SeoLandingPageFilterRedirectResolver
{
    /** @param TaxonRepositoryInterface<Taxon> $taxons */
    public function __construct(
        private SeoLandingPageRepository $pages,
        private TaxonRepositoryInterface $taxons,
        private ChannelContextInterface $channels,
        private LocaleContextInterface $locales,
        private ProductFacetDefinitionService $facets,
        private LoggerInterface $logger,
    ) {
    }

    public function resolve(Request $request): ?string
    {
        if ($request->attributes->has('cardnext_seo_landing_page')) {
            return null;
        }

        $criteria = $this->criteriaArray($request->query->all()['criteria'] ?? null);
        if ($criteria === null || $criteria === []) {
            return null;
        }

        $channel = $this->channels->getChannel();
        $locale = $this->locales->getLocaleCode();
        $slug = $request->attributes->getString('slug');
        $taxon = $slug === '' ? null : $this->taxons->findOneBySlug($slug, $locale);
        if (!$channel instanceof Channel || !$taxon instanceof Taxon) {
            return null;
        }

        $signature = $this->criteriaSignature($criteria, $taxon, $locale);
        if ($signature === null || $signature === []) {
            return null;
        }

        $matches = array_values(array_filter(
            $this->pages->findEnabledForTaxon($channel, $locale, $taxon),
            fn (SeoLandingPage $page): bool => $this->canonicalize($page->getFilterDefinition()) === $signature,
        ));

        if (count($matches) > 1) {
            $this->logger->warning('Multiple SEO landing pages match the same filter definition.', [
                'channel' => $channel->getCode(),
                'locale' => $locale,
                'taxon' => $taxon->getCode(),
            ]);

            return null;
        }
        if ($matches === []) {
            return null;
        }

        $query = $request->query->all();
        unset($query['criteria']);
        if (($query['page'] ?? null) === '1' || ($query['page'] ?? null) === 1) unset($query['page']);
        if (($query['limit'] ?? null) === '9' || ($query['limit'] ?? null) === 9) unset($query['limit']);
        $suffix = $query === [] ? '' : '?' . http_build_query($query, '', '&', \PHP_QUERY_RFC3986);

        $path = LandingPagePath::normalize($matches[0]->getPath());

        return $path . $suffix;
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{manufacturers?: list<string>, attributes?: array<string, list<string>>}|null
     */
    public function criteriaSignature(array $criteria, Taxon $taxon, string $locale): ?array
    {
        $profile = $this->facets->profileForTaxon($taxon);
        if ($profile === null) return null;

        $facetAttributes = [];
        foreach ($this->facets->forProfile($profile, $locale) as $facet) {
            $facetAttributes[$facet['name']] = $facet['attribute'];
        }

        $signature = [];
        foreach ($criteria as $name => $data) {
            $values = FilterDataNormalizer::values($data);
            if ($values === []) continue;
            if ($name === 'manufacturer') {
                $signature['manufacturers'] = $values;
                continue;
            }
            if (!isset($facetAttributes[$name])) return null;
            $signature['attributes'][$facetAttributes[$name]] = $values;
        }

        return $this->canonicalize($signature);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array{manufacturers?: list<string>, attributes?: array<string, list<string>>}
     */
    public function canonicalize(array $definition): array
    {
        $result = [];
        $manufacturers = $this->canonicalValues($definition['manufacturers'] ?? []);
        if ($manufacturers !== []) $result['manufacturers'] = $manufacturers;

        $attributes = is_array($definition['attributes'] ?? null) ? $definition['attributes'] : [];
        foreach ($attributes as $code => $values) {
            if (!is_string($code) || trim($code) === '') continue;
            $canonical = $this->canonicalValues($values);
            if ($canonical !== []) $result['attributes'][trim($code)] = $canonical;
        }
        if (isset($result['attributes'])) ksort($result['attributes'], \SORT_STRING);

        return $result;
    }

    /** @return list<string> */
    private function canonicalValues(mixed $values): array
    {
        if (!is_array($values)) return [];
        $values = array_map(static fn (mixed $value): string => trim((string) $value), array_filter($values, 'is_scalar'));
        $values = array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
        sort($values, \SORT_STRING);

        return $values;
    }

    /** @return array<string, mixed>|null */
    private function criteriaArray(mixed $criteria): ?array
    {
        if (!is_array($criteria)) return null;
        $result = [];
        foreach ($criteria as $key => $value) {
            if (!is_string($key)) return null;
            $result[$key] = $value;
        }

        return $result;
    }
}
