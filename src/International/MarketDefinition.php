<?php

declare(strict_types=1);

namespace App\International;

final readonly class MarketDefinition
{
    public function __construct(
        public string $channelCode,
        public string $name,
        public string $hostname,
        public string $localeCode,
        public string $currencyCode,
        public string $countryCode,
    ) {
    }
}
