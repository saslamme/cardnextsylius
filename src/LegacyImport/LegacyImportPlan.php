<?php

declare(strict_types=1);

namespace App\LegacyImport;

final class LegacyImportPlan
{
    /** @param list<LegacyProductRecord> $records @param array<string,mixed> $report @param list<string> $reviewKeys */
    public function __construct(public readonly array $records, public readonly array $report, public readonly array $reviewKeys = [])
    {
    }
}
