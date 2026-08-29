<?php
declare(strict_types=1);
namespace App\Service\Quote;
final class QuoteIssuerProfileRegistry
{
    /** Structured profiles intentionally contain no invented registration or banking data. */
    private const PROFILES = [
        'CARDNEXT_DE' => ['company'=>'Cardnext','street'=>'','postalCode'=>'','city'=>'','country'=>'Deutschland','email'=>'','phone'=>'','website'=>'www.cardnext.de','vatId'=>'','registrationCourt'=>'','registrationNumber'=>'','managingDirector'=>'','bankName'=>null,'iban'=>null,'bic'=>null],
        'CARDNEXT_AT' => ['company'=>'Cardnext','street'=>'','postalCode'=>'','city'=>'','country'=>'Österreich','email'=>'','phone'=>'','website'=>'www.cardnext.at','vatId'=>'','registrationCourt'=>'','registrationNumber'=>'','managingDirector'=>'','bankName'=>null,'iban'=>null,'bic'=>null],
    ];
    /** @return array<string,string|null> */
    public function get(string $channelCode): array
    {
        if (!isset(self::PROFILES[$channelCode])) throw new \InvalidArgumentException('Für diesen Verkaufskanal ist kein Ausstellerprofil konfiguriert.');
        return self::PROFILES[$channelCode];
    }
}
