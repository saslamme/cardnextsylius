<?php

declare(strict_types=1);

namespace App\CustomerImport;

use App\Entity\Channel\Channel;

final readonly class LegacyCustomerImportOptions
{
    public function __construct(
        public Channel $channel,
        public string $encoding = 'ISO-8859-1',
        public bool $dryRun = true,
        public bool $updateExisting = false,
        public bool $skipExisting = false,
        public ?int $limit = null,
    ) {
    }
}
