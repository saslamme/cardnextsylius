<?php

declare(strict_types=1);

namespace App\LegacyImport;

final class LegacyProductRecord
{
    /**
     * @param list<string> $taxonCodes
     * @param array<string,mixed> $attributes
     * @param list<string> $compatibilityReferences
     * @param list<string> $rawData
     * @param list<string> $reviewReasons
     * @param list<string> $relatedProductCodes
     */
    public function __construct(
        public readonly string $legacyId,
        public readonly string $sourceFile,
        public readonly string $manufacturer,
        public readonly string $manufacturerPartNumber,
        public readonly string $name,
        public readonly ?int $price,
        public readonly string $description,
        public readonly ?string $gtin,
        public readonly array $taxonCodes,
        public readonly array $attributes,
        public readonly array $compatibilityReferences,
        public readonly bool $archived,
        public readonly bool $hasImage,
        public readonly array $rawData,
        public readonly string $model = '',
        public readonly array $reviewReasons = [],
        public readonly array $relatedProductCodes = [],
    ) {
    }
}
