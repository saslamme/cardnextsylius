<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProductAttributeVisibilityExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cardnext_visible_product_attributes', [$this, 'visibleAttributes']),
        ];
    }

    /**
     * @param iterable<mixed> $attributes
     *
     * @return list<mixed>
     */
    public function visibleAttributes(iterable $attributes): array
    {
        $visible = [];

        foreach ($attributes as $attributeValue) {
            if (!is_object($attributeValue) || !method_exists($attributeValue, 'getValue')) {
                continue;
            }

            if ($this->hasValue($attributeValue->getValue())) {
                $visible[] = $attributeValue;
            }
        }

        return $visible;
    }

    private function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasValue($item)) {
                    return true;
                }
            }

            return false;
        }

        // Boolean false and numeric 0 are deliberately considered valid values.
        return true;
    }
}
