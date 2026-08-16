<?php

declare(strict_types=1);

namespace App\LegacyImport;

final class LegacyProductRecord
{
    /** @param list<string> $taxonCodes @param array<string,string> $attributes @param list<string> $compatibilityReferences */
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
    ) {
    }
}
