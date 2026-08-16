<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product\Product;
use App\Entity\Product\ProductAttributeValue;
use App\Entity\Product\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CardnextResearchAttributeCsvImporter
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{
     *     rows:int,
     *     products_found:int,
     *     products_missing:int,
     *     ambiguous_matches:int,
     *     status_skipped:int,
     *     empty_attributes:int,
     *     candidate_values:int,
     *     values_would_write:int,
     *     values_written:int,
     *     existing_values_skipped:int,
     *     missing_slots:int,
     *     invalid_values:int,
     *     manufacturer_mismatches:int,
     *     changes:list<array{mpn:string,product:string,attribute:string,old:mixed,new:mixed}>,
     *     warnings:list<string>
     * }
     */
    public function import(
        string $csvPath,
        bool $dryRun = false,
        bool $overwrite = false,
        bool $includeAmbiguous = false,
    ): array {
        if (!is_readable($csvPath)) {
            throw new \RuntimeException(sprintf('CSV "%s" is not readable.', $csvPath));
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Could not open CSV "%s".', $csvPath));
        }

        $header = fgetcsv($handle, 0, ';', '"', '\\');
        if (!is_array($header)) {
            fclose($handle);
            throw new \RuntimeException('CSV header is missing.');
        }

        $header = array_map(static function (mixed $value): string {
            $value = (string) $value;
            return ltrim(trim($value), "\xEF\xBB\xBF");
        }, $header);

        $required = ['Hersteller', 'Titel', 'Hersteller-Artikelnummer', 'attributes_json', 'research_status'];
        foreach ($required as $column) {
            if (!in_array($column, $header, true)) {
                fclose($handle);
                throw new \RuntimeException(sprintf('Required CSV column "%s" is missing.', $column));
            }
        }

        $indexes = array_flip($header);
        $variantRepository = $this->entityManager->getRepository(ProductVariant::class);

        $result = [
            'rows' => 0,
            'products_found' => 0,
            'products_missing' => 0,
            'ambiguous_matches' => 0,
            'status_skipped' => 0,
            'empty_attributes' => 0,
            'candidate_values' => 0,
            'values_would_write' => 0,
            'values_written' => 0,
            'existing_values_skipped' => 0,
            'missing_slots' => 0,
            'invalid_values' => 0,
            'manufacturer_mismatches' => 0,
            'changes' => [],
            'warnings' => [],
        ];

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            ++$result['rows'];

            $manufacturer = trim((string) ($row[$indexes['Hersteller']] ?? ''));
            $title = trim((string) ($row[$indexes['Titel']] ?? ''));
            $mpn = trim((string) ($row[$indexes['Hersteller-Artikelnummer']] ?? ''));
            $status = trim((string) ($row[$indexes['research_status']] ?? ''));
            $attributesJson = trim((string) ($row[$indexes['attributes_json']] ?? ''));

            if (!in_array($status, ['complete', 'partial'], true) && !($includeAmbiguous && $status === 'ambiguous')) {
                ++$result['status_skipped'];
                continue;
            }

            if ($mpn === '') {
                ++$result['products_missing'];
                $this->warning($result, sprintf('Row %d (%s): manufacturer part number is empty.', $result['rows'] + 1, $title));
                continue;
            }

            $normalizedMpn = self::normalizeIdentifier($mpn);
            if ($normalizedMpn === '') {
                ++$result['products_missing'];
                continue;
            }

            /** @var list<ProductVariant> $variants */
            $variants = $variantRepository->findBy(['manufacturerPartNumberNormalized' => $normalizedMpn]);
            if ($variants === []) {
                ++$result['products_missing'];
                $this->warning($result, sprintf('%s: no variant found for MPN %s.', $title, $mpn));
                continue;
            }

            if (count($variants) > 1) {
                $variants = $this->filterByManufacturer($variants, $manufacturer);
            }

            if (count($variants) !== 1) {
                ++$result['ambiguous_matches'];
                $this->warning($result, sprintf('%s: MPN %s matches %d variants.', $title, $mpn, count($variants)));
                continue;
            }

            $variant = $variants[0];
            $product = $variant->getProduct();
            if (!$product instanceof Product) {
                ++$result['products_missing'];
                continue;
            }

            ++$result['products_found'];

            if ($manufacturer !== '') {
                $shopManufacturer = $product->getManufacturer()?->getName() ?? '';
                if ($shopManufacturer !== '' && self::normalizeText($manufacturer) !== self::normalizeText($shopManufacturer)) {
                    ++$result['manufacturer_mismatches'];
                    $this->warning($result, sprintf(
                        '%s / %s: CSV manufacturer "%s" differs from shop manufacturer "%s".',
                        (string) $product->getCode(),
                        $mpn,
                        $manufacturer,
                        $shopManufacturer,
                    ));
                }
            }

            try {
                $attributes = $attributesJson !== '' ? json_decode($attributesJson, true, 512, JSON_THROW_ON_ERROR) : [];
            } catch (\JsonException $exception) {
                ++$result['invalid_values'];
                $this->warning($result, sprintf('%s / %s: invalid attributes_json: %s', (string) $product->getCode(), $mpn, $exception->getMessage()));
                continue;
            }

            if (!is_array($attributes) || $attributes === []) {
                ++$result['empty_attributes'];
                continue;
            }

            $slots = $this->attributeSlots($product);

            foreach ($attributes as $code => $rawValue) {
                $code = (string) $code;
                if (!str_starts_with($code, 'CN_')) {
                    ++$result['invalid_values'];
                    continue;
                }

                ++$result['candidate_values'];

                $slot = $slots[$code] ?? null;
                if (!$slot instanceof ProductAttributeValue) {
                    ++$result['missing_slots'];
                    $this->warning($result, sprintf('%s / %s: attribute %s is not part of the product profile.', (string) $product->getCode(), $mpn, $code));
                    continue;
                }

                $normalized = $this->normalizeForSlot($slot, $rawValue);
                if (!$this->hasValue($normalized)) {
                    ++$result['invalid_values'];
                    $this->warning($result, sprintf('%s / %s: invalid value for %s.', (string) $product->getCode(), $mpn, $code));
                    continue;
                }

                $current = $slot->getValue();
                if (!$overwrite && $this->hasValue($current)) {
                    ++$result['existing_values_skipped'];
                    continue;
                }

                if ($this->sameValue($current, $normalized)) {
                    continue;
                }

                ++$result['values_would_write'];
                if (count($result['changes']) < 150) {
                    $result['changes'][] = [
                        'mpn' => $mpn,
                        'product' => (string) $product->getCode(),
                        'attribute' => $code,
                        'old' => $current,
                        'new' => $normalized,
                    ];
                }

                if (!$dryRun) {
                    $slot->setValue($normalized);
                    ++$result['values_written'];
                }
            }
        }

        fclose($handle);

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }

    /** @param list<ProductVariant> $variants @return list<ProductVariant> */
    private function filterByManufacturer(array $variants, string $manufacturer): array
    {
        if ($manufacturer === '') {
            return $variants;
        }

        $needle = self::normalizeText($manufacturer);
        $filtered = array_values(array_filter($variants, static function (ProductVariant $variant) use ($needle): bool {
            $product = $variant->getProduct();
            if (!$product instanceof Product) {
                return false;
            }

            return self::normalizeText($product->getManufacturer()?->getName() ?? '') === $needle;
        }));

        return $filtered !== [] ? $filtered : $variants;
    }

    /** @return array<string, ProductAttributeValue> */
    private function attributeSlots(Product $product): array
    {
        $slots = [];
        foreach ($product->getAttributes() as $value) {
            if ($value instanceof ProductAttributeValue && $value->getCode() !== null) {
                $slots[(string) $value->getCode()] = $value;
            }
        }

        return $slots;
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
            'json' => $this->toSelectValues($rawValue, (array) ($attribute->getConfiguration()['choices'] ?? []), (bool) ($attribute->getConfiguration()['multiple'] ?? false)),
            default => $this->toText($rawValue),
        };
    }

    /** @param array<string, mixed> $choices */
    private function toSelectValues(mixed $rawValue, array $choices, bool $multiple): ?array
    {
        $values = is_array($rawValue) ? $rawValue : [$rawValue];
        $values = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $values,
        ), static fn (string $value): bool => $value !== '')));

        if ($choices !== []) {
            $allowed = array_keys($choices);
            $values = array_values(array_intersect($values, $allowed));
        }

        if ($values === [] || (!$multiple && count($values) !== 1)) {
            return null;
        }

        return $values;
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

        return null;
    }

    private function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (!is_scalar($value)) {
            return null;
        }
        $value = str_replace(',', '.', trim((string) $value));

        return is_numeric($value) ? (float) $value : null;
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

    /** @param array<string,mixed> $result */
    private function warning(array &$result, string $message): void
    {
        if (count($result['warnings']) < 100) {
            $result['warnings'][] = $message;
        }
    }

    private static function normalizeIdentifier(string $value): string
    {
        $value = mb_strtolower(trim($value));
        return preg_replace('/[^a-z0-9]+/i', '', $value) ?? '';
    }

    private static function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return preg_replace('/[^a-z0-9]+/i', '', $value) ?? '';
    }
}
