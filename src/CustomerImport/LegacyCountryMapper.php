<?php

declare(strict_types=1);

namespace App\CustomerImport;

final class LegacyCountryMapper
{
    private const COUNTRIES = [
        'deutschland' => 'DE', 'germany' => 'DE',
        'österreich' => 'AT', 'austria' => 'AT',
        'schweiz' => 'CH', 'switzerland' => 'CH',
        'niederlande' => 'NL', 'netherlands' => 'NL',
        'dänemark' => 'DK', 'denmark' => 'DK',
        'schweden' => 'SE', 'sweden' => 'SE',
        'italien' => 'IT', 'italy' => 'IT',
        'spanien' => 'ES', 'spain' => 'ES',
        'frankreich' => 'FR', 'france' => 'FR',
        'polen' => 'PL', 'poland' => 'PL', 'portugal' => 'PT',
        'norwegen' => 'NO', 'norway' => 'NO', 'ungarn' => 'HU', 'hungary' => 'HU',
    ];

    public function map(string $country): ?string
    {
        $normalized = mb_strtolower(trim($country));

        return self::COUNTRIES[$normalized] ?? null;
    }
}
