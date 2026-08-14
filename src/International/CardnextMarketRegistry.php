<?php

declare(strict_types=1);

namespace App\International;

final class CardnextMarketRegistry
{
    /**
     * @return array<string, MarketDefinition>
     */
    public function all(): array
    {
        return [
            'CARDNEXT_DE' => new MarketDefinition(
                'CARDNEXT_DE',
                'Cardnext Deutschland',
                'www.cardnext.de',
                'de_DE',
                'EUR',
                'DE',
            ),
            'CARDNEXT_AT' => new MarketDefinition(
                'CARDNEXT_AT',
                'Cardnext Österreich',
                'www.cardnext.at',
                'de_AT',
                'EUR',
                'AT',
            ),
            'CARDNEXT_CH' => new MarketDefinition(
                'CARDNEXT_CH',
                'Cardnext Schweiz',
                'www.cardnext.ch',
                'de_CH',
                'CHF',
                'CH',
            ),
            'CARDNEXT_NL' => new MarketDefinition(
                'CARDNEXT_NL',
                'Cardnext Nederland',
                'www.cardnext.nl',
                'nl_NL',
                'EUR',
                'NL',
            ),
            'CARDNEXT_DK' => new MarketDefinition(
                'CARDNEXT_DK',
                'Cardnext Danmark',
                'www.cardnext.dk',
                'da_DK',
                'DKK',
                'DK',
            ),
            'CARDNEXT_SE' => new MarketDefinition(
                'CARDNEXT_SE',
                'Cardnext Sverige',
                'www.cardnext.se',
                'sv_SE',
                'SEK',
                'SE',
            ),
            'CARDNEXT_NO' => new MarketDefinition(
                'CARDNEXT_NO',
                'Cardnext Norge',
                'www.cardnext.no',
                'nb_NO',
                'NOK',
                'NO',
            ),
            'CARDNEXT_IT' => new MarketDefinition(
                'CARDNEXT_IT',
                'Cardnext Italia',
                'www.cardnext.it',
                'it_IT',
                'EUR',
                'IT',
            ),
            'CARDNEXT_ES' => new MarketDefinition(
                'CARDNEXT_ES',
                'Cardnext España',
                'www.cardnext.es',
                'es_ES',
                'EUR',
                'ES',
            ),
            'CARDNEXT_PL' => new MarketDefinition(
                'CARDNEXT_PL',
                'Cardnext Polska',
                'www.cardnext.pl',
                'pl_PL',
                'PLN',
                'PL',
            ),
            'CARDNEXT_PT' => new MarketDefinition(
                'CARDNEXT_PT',
                'Cardnext Portugal',
                'www.cardnext.pt',
                'pt_PT',
                'EUR',
                'PT',
            ),
            'CARDNEXT_HU' => new MarketDefinition(
                'CARDNEXT_HU',
                'Cardnext Magyarország',
                'www.cardnext.hu',
                'hu_HU',
                'HUF',
                'HU',
            ),
            'CARDNEXT_UA' => new MarketDefinition(
                'CARDNEXT_UA',
                'Cardnext Україна',
                'www.cardnext.ua',
                'uk_UA',
                'UAH',
                'UA',
            ),
        ];
    }

    public function get(string $channelCode): ?MarketDefinition
    {
        return $this->all()[$channelCode] ?? null;
    }
}
