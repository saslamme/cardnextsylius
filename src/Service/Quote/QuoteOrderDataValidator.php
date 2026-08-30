<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Quote\Quote;
use Symfony\Component\Intl\Countries;

final class QuoteOrderDataValidator
{
    /** @return list<string> */
    public function missingRequiredFields(Quote $quote): array
    {
        $fields = [
            'Ansprechpartner' => $quote->getCustomerContactName(),
            'E-Mail' => $quote->getCustomerEmail(),
            'Straße' => $quote->getCustomerStreet(),
            'Hausnummer' => $quote->getCustomerHouseNumber(),
            'Postleitzahl' => $quote->getCustomerPostalCode(),
            'Ort' => $quote->getCustomerCity(),
            'Land' => $quote->getCustomerCountryCode(),
        ];
        $missing = [];
        foreach ($fields as $label => $value) {
            if ($value === null || trim($value) === '') $missing[] = $label;
        }
        if (!in_array('Ansprechpartner', $missing, true) && preg_match('/^\S+\s+\S+/u', trim($quote->getCustomerContactName())) !== 1) $missing[] = 'Ansprechpartner';
        if (!in_array('E-Mail', $missing, true) && !filter_var($quote->getCustomerEmail(), FILTER_VALIDATE_EMAIL)) $missing[] = 'E-Mail';
        if (!in_array('Land', $missing, true) && !Countries::exists(strtoupper(trim((string) $quote->getCustomerCountryCode())))) $missing[] = 'Land';

        return $missing;
    }

    public function assertCompleteForOrder(Quote $quote): void
    {
        $this->assertComplete($quote, 'Die Bestellung kann noch nicht erstellt werden. In den Kundendaten fehlen: %s.');
    }

    public function assertCompleteForReady(Quote $quote): void
    {
        $this->assertComplete($quote, 'Das Angebot kann noch nicht fertiggestellt werden. Bitte ergänzen Sie zunächst: %s.');
    }

    private function assertComplete(Quote $quote, string $message): void
    {
        $missing = $this->missingRequiredFields($quote);
        if ($missing !== []) throw new \DomainException(sprintf($message, implode(', ', $missing)));
    }
}
