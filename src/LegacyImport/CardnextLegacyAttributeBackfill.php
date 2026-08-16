<?php

declare(strict_types=1);

namespace App\LegacyImport;

use App\Entity\Product\Product;
use App\Entity\Product\ProductAttributeValue;
use Doctrine\ORM\EntityManagerInterface;

final class CardnextLegacyAttributeBackfill
{
    public function __construct(
        private readonly CardnextLegacySourceParser $parser,
        private readonly LegacyDescriptionAttributeExtractor $descriptionExtractor,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     products_scanned:int,
     *     products_found:int,
     *     products_changed:int,
     *     products_missing:int,
     *     candidate_values:int,
     *     values_written:int,
     *     values_would_write:int,
     *     existing_values_skipped:int,
     *     missing_profile_slots:int,
     *     invalid_values_skipped:int,
     *     unchanged_values:int,
     *     changes:list<array{product:string,name:string,attribute:string,old:mixed,new:mixed}>
     * }
     */
    public function backfill(
        string $zipPath,
        bool $dryRun = false,
        bool $overwrite = false,
        ?string $onlyProductCode = null,
    ): array {
        $plan = $this->parser->parse($zipPath);
        $productRepository = $this->entityManager->getRepository(Product::class);

        $result = [
            'products_scanned' => 0,
            'products_found' => 0,
            'products_changed' => 0,
            'products_missing' => 0,
            'candidate_values' => 0,
            'values_written' => 0,
            'values_would_write' => 0,
            'existing_values_skipped' => 0,
            'missing_profile_slots' => 0,
            'invalid_values_skipped' => 0,
            'unchanged_values' => 0,
            'changes' => [],
        ];

        foreach ($plan->records as $record) {
            if ($record->taxonCodes === []) {
                continue;
            }

            $productCode = CardnextLegacySourceParser::productCode($record);
            if ($onlyProductCode !== null && $onlyProductCode !== '' && $productCode !== $onlyProductCode) {
                continue;
            }

            ++$result['products_scanned'];

            /** @var Product|null $product */
            $product = $productRepository->findOneBy(['code' => $productCode]);
            if (!$product instanceof Product) {
                ++$result['products_missing'];
                continue;
            }

            ++$result['products_found'];

            // Description-derived data fills the gaps. Explicit legacy attribute data
            // wins whenever it contains a real value.
            $candidates = $this->descriptionExtractor->extract($record->description);
            foreach ($record->attributes as $attributeCode => $value) {
                if ($this->hasValue($value)) {
                    $candidates[(string) $attributeCode] = $value;
                }
            }

            $productChanged = false;

            foreach ($candidates as $attributeCode => $rawValue) {
                ++$result['candidate_values'];

                $slot = $this->findAttributeSlot($product, (string) $attributeCode);
                if (!$slot instanceof ProductAttributeValue) {
                    ++$result['missing_profile_slots'];
                    continue;
                }

                $normalized = $this->normalizeForSlot($slot, $rawValue);
                if (!$this->hasValue($normalized)) {
                    ++$result['invalid_values_skipped'];
                    continue;
                }

                $current = $slot->getValue();
                if (!$overwrite && $this->hasValue($current)) {
                    ++$result['existing_values_skipped'];
                    continue;
                }

                if ($this->sameValue($current, $normalized)) {
                    ++$result['unchanged_values'];
                    continue;
                }

                ++$result['values_would_write'];
                $productChanged = true;

                if (count($result['changes']) < 100) {
                    $result['changes'][] = [
                        'product' => $productCode,
                        'name' => (string) $product->getName(),
                        'attribute' => (string) $attributeCode,
                        'old' => $current,
                        'new' => $normalized,
                    ];
                }

                if (!$dryRun) {
                    $slot->setValue($normalized);
                    ++$result['values_written'];
                }
            }

            if ($productChanged) {
                ++$result['products_changed'];
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }

    private function findAttributeSlot(Product $product, string $attributeCode): ?ProductAttributeValue
    {
        foreach ($product->getAttributes() as $attributeValue) {
            if (!$attributeValue instanceof ProductAttributeValue) {
                continue;
            }

            if ($attributeValue->getCode() === $attributeCode) {
                return $attributeValue;
            }
        }

        return null;
    }

    private function normalizeForSlot(ProductAttributeValue $slot, mixed $rawValue): mixed
    {
        $attribute = $slot->getAttribute();
        if ($attribute === null) {
            return null;
        }

        return match ($attribute->getStorageType()) {
            'boolean' => $this->toBoolean($rawValue),
            'integer' => $this->toInteger($rawValue),
            'float' => $this->toFloat($rawValue),
            'json' => $this->toSelectValues($rawValue, (array) ($attribute->getConfiguration()['choices'] ?? [])),
            default => $this->toText($rawValue),
        };
    }

    /** @param array<string, mixed> $choices */
    private function toSelectValues(mixed $value, array $choices): ?array
    {
        $values = is_array($value) ? $value : [$value];
        $values = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $values,
        ), static fn (string $item): bool => $item !== '')));

        if ($choices !== []) {
            $allowed = array_keys($choices);
            $values = array_values(array_intersect($values, $allowed));
        }

        return $values !== [] ? $values : null;
    }

    private function toBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (!is_scalar($value)) {
            return null;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private function toInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_scalar($value) && preg_match('/-?\d+/', (string) $value, $match)) {
            return (int) $match[0];
        }

        return null;
    }

    private function toFloat(mixed $value): ?float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $normalized = str_replace(',', '.', (string) $value);
        if (!preg_match('/-?\d+(?:\.\d+)?/', $normalized, $match)) {
            return null;
        }

        return (float) $match[0];
    }

    private function toText(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : null;
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
            return $value !== [];
        }

        // false and 0 are valid technical values.
        return true;
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        if (is_array($left) && is_array($right)) {
            $left = array_values(array_map('strval', $left));
            $right = array_values(array_map('strval', $right));
            sort($left);
            sort($right);
            return $left === $right;
        }

        return $left === $right;
    }
}
