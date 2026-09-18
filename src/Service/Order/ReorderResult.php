<?php

declare(strict_types=1);

namespace App\Service\Order;

final readonly class ReorderResult
{
    public function __construct(
        public int $addedItems,
        public int $skippedItems,
        public int $skippedConfiguredItems,
        public int $skippedBundles,
        public int $skippedProtectionItems,
    ) {
    }

    public function skippedTotal(): int
    {
        return $this->skippedItems + $this->skippedConfiguredItems + $this->skippedBundles;
    }
}
