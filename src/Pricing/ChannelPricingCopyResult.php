<?php

declare(strict_types=1);

namespace App\Pricing;

final class ChannelPricingCopyResult
{
    /** @var list<ChannelPricingCopyRow> */
    public array $rows = [];

    public int $scanned = 0;

    public int $eligible = 0;

    public int $created = 0;

    public int $overwritten = 0;

    public int $skippedExisting = 0;

    public int $skippedMissingSource = 0;

    public int $skippedNotInTargetChannel = 0;

    public int $skippedInvalidSource = 0;

    public function skipped(): int
    {
        return $this->skippedExisting + $this->skippedMissingSource + $this->skippedNotInTargetChannel + $this->skippedInvalidSource;
    }
}
