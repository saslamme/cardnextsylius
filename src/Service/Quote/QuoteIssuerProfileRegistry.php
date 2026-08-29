<?php

declare(strict_types=1);

namespace App\Service\Quote;

final class QuoteIssuerProfileRegistry
{
    private const FIELDS = [
        'company',
        'street',
        'postalCode',
        'city',
        'country',
        'email',
        'phone',
        'website',
        'vatId',
        'registrationCourt',
        'registrationNumber',
        'managingDirector',
        'bankName',
        'iban',
        'bic',
    ];

    /** @param array<string, array<string, string|null>> $profiles */
    public function __construct(private readonly array $profiles)
    {
        foreach ($profiles as $channelCode => $profile) {
            $unknownFields = array_diff(array_keys($profile), self::FIELDS);
            if ($unknownFields !== []) {
                throw new \InvalidArgumentException(sprintf(
                    'Unbekannte Felder im Ausstellerprofil "%s": %s',
                    $channelCode,
                    implode(', ', $unknownFields),
                ));
            }
        }
    }

    /** @return array<string, string|null> */
    public function get(string $channelCode): array
    {
        if (!isset($this->profiles[$channelCode])) {
            throw new \InvalidArgumentException('Für diesen Verkaufskanal ist kein Ausstellerprofil konfiguriert.');
        }

        return array_replace(array_fill_keys(self::FIELDS, null), $this->profiles[$channelCode]);
    }
}
