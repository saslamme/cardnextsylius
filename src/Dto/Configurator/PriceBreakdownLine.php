<?php

declare(strict_types=1);

namespace App\Dto\Configurator;

final readonly class PriceBreakdownLine implements \JsonSerializable
{
    public function __construct(public string $sourceType, public string $sourceCode, public ?string $fieldCode, public ?string $valueCode, public string $chargeCode, public string $priceType, public ?string $label, public ?int $unitAmount, public ?int $baseAmount, public int $multiplier, public int $amount)
    {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
