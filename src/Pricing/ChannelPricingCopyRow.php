<?php

declare(strict_types=1);

namespace App\Pricing;

final readonly class ChannelPricingCopyRow
{
    public function __construct(
        public string $product,
        public string $variant,
        public ?string $sku,
        public ?int $sourcePrice,
        public ?int $currentTargetPrice,
        public ?int $newTargetPrice,
        public string $action,
    ) {
    }
}
