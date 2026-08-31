<?php

declare(strict_types=1);

namespace App\International;

final readonly class MarketDefinition
{
    public function __construct(
        public string $channelCode,
        public string $name,
        public string $countryDisplayName,
        public string $hostname,
        public string $scheme,
        public string $localeCode,
        public string $currencyCode,
        public string $countryCode,
        public bool $enabled,
    ) {
    }

    public function baseUrl(): string
    {
        return sprintf('%s://%s', $this->scheme, $this->hostname);
    }

    public function hreflang(): string
    {
        return str_replace('_', '-', $this->localeCode);
    }
}
