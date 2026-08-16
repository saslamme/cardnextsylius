<?php

declare(strict_types=1);

namespace App\Service;

use Sylius\Component\Attribute\AttributeType\CheckboxAttributeType;

/**
 * Read-only facade for shop facets. The product profiles remain the single source
 * of truth; this class only adapts their metadata for the grid layer.
 */
final readonly class ProductFacetDefinitionService
{
    public function __construct(private ProductAttributeProfileService $profiles)
    {
    }

    /**
     * @return list<array{name: string, label: string, type: 'select'|'boolean', attribute: string, choices: array<string, string>}>
     */
    public function forProfile(string $profileCode, string $locale = 'de_DE'): array
    {
        $facets = [];
        foreach ($this->profiles->getFilterableDefinitionsForProfile($profileCode, $locale) as $code => $definition) {
            $choices = [];
            foreach ($definition['choices'] as $value => $label) {
                $choices[$label] = (string) $value;
            }
            $facets[] = [
                'name' => strtolower($code),
                'label' => $definition['name'],
                'type' => $definition['type'] === CheckboxAttributeType::TYPE ? 'boolean' : 'select',
                'attribute' => $code,
                // Symfony choice arrays use display label => submitted technical value.
                'choices' => $choices,
            ];
        }

        return $facets;
    }

    public function hasProfile(string $profileCode): bool
    {
        return $this->profiles->getFilterableDefinitionsForProfile($profileCode, 'de_DE') !== [];
    }
}
