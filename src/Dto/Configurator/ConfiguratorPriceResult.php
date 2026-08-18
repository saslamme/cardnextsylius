<?php

declare(strict_types=1);

namespace App\Dto\Configurator;

final readonly class ConfiguratorPriceResult implements \JsonSerializable
{
    /** @param list<PriceBreakdownLine> $breakdown */
    public function __construct(public int $quantity, public string $currencyCode, public int $baseUnitAmount, public int $optionsUnitAmount, public int $unitAmount, public int $unitTotal, public int $fixedTotal, public int $percentageTotal, public int $total, public array $breakdown, public ?string $leadTimeCode = null, public ?string $leadTimeName = null, public ?int $workingDays = null)
    {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
