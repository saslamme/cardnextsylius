<?php

declare(strict_types=1);

namespace App\Seo;

use App\Entity\Taxonomy\Taxon;
use App\Service\ProductFacetDefinitionService;

/** Converts the visual builder's submission to the existing storefront format. */
final readonly class SeoLandingPageFilterDefinition
{
    public function __construct(private ProductFacetDefinitionService $facets)
    {
    }

    /** @param array<string, mixed> $submitted @return array<string, mixed> */
    public function normalize(array $submitted, ?Taxon $taxon, string $locale): array
    {
        if (!$taxon instanceof Taxon || null === $profile = $this->facets->profileForTaxon($taxon)) {
            return [];
        }

        $result = [];
        $manufacturers = $this->scalarList($submitted['manufacturers'] ?? []);
        if ($manufacturers !== []) {
            $result['manufacturers'] = $manufacturers;
        }

        $allowed = [];
        foreach ($this->facets->forProfile($profile, $locale) as $facet) {
            $allowed[$facet['attribute']] = array_values($facet['choices']);
        }
        $attributes = is_array($submitted['attributes'] ?? null) ? $submitted['attributes'] : [];
        foreach ($allowed as $code => $choices) {
            $values = array_values(array_intersect($this->scalarList($attributes[$code] ?? []), $choices));
            if ($values !== []) {
                $result['attributes'][$code] = $values;
            }
        }

        return $result;
    }

    /** @return list<string> */
    private function scalarList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', array_filter($value, static fn (mixed $item): bool => is_scalar($item) && trim((string) $item) !== ''))));
    }
}
