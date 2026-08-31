<?php

declare(strict_types=1);

namespace App\International;

final class CardnextMarketRegistry
{
    /** @var array<string, MarketDefinition> */
    private array $markets;

    public function __construct()
    {
        $this->markets = [];
        foreach ([
            ['CARDNEXT_DE', 'Cardnext Deutschland', 'Deutschland', 'www.cardnext.de', 'de_DE', 'EUR', 'DE'],
            ['CARDNEXT_AT', 'Cardnext Österreich', 'Österreich', 'at.cardnext.de', 'de_AT', 'EUR', 'AT'],
            ['CARDNEXT_DK', 'Cardnext Danmark', 'Danmark', 'dk.cardnext.de', 'da_DK', 'DKK', 'DK'],
            ['CARDNEXT_ES', 'Cardnext España', 'España', 'es.cardnext.de', 'es_ES', 'EUR', 'ES'],
            ['CARDNEXT_IT', 'Cardnext Italia', 'Italia', 'it.cardnext.de', 'it_IT', 'EUR', 'IT'],
            ['CARDNEXT_NL', 'Cardnext Nederland', 'Nederland', 'nl.cardnext.de', 'nl_NL', 'EUR', 'NL'],
            ['CARDNEXT_SE', 'Cardnext Sverige', 'Sverige', 'se.cardnext.de', 'sv_SE', 'SEK', 'SE'],
        ] as [$code, $name, $countryName, $hostname, $locale, $currency, $country]) {
            $this->markets[$code] = new MarketDefinition($code, $name, $countryName, $hostname, 'https', $locale, $currency, $country, true);
        }
    }

    /** @return array<string, MarketDefinition> */
    public function all(): array
    {
        return $this->markets;
    }

    public function get(string $channelCode): ?MarketDefinition
    {
        return $this->markets[$channelCode] ?? null;
    }

    public function forHostname(string $hostname): ?MarketDefinition
    {
        $hostname = strtolower(preg_replace('/:\d+$/', '', trim($hostname)) ?? '');
        foreach ($this->markets as $market) {
            if ($market->hostname === $hostname) {
                return $market;
            }
        }

        return null;
    }
}
