<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Quote\Quote;
use App\Enum\Quote\QuoteStatus;
use App\Service\Quote\QuoteOrderDataValidator;
use PHPUnit\Framework\TestCase;

final class QuoteOrderDataValidatorTest extends TestCase
{
    private QuoteOrderDataValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new QuoteOrderDataValidator();
    }

    public function testCompleteOrderDataIsAcceptedForReadyAndConversion(): void
    {
        $quote = $this->completeQuote();
        $this->validator->assertCompleteForReady($quote);
        $quote->transitionTo(QuoteStatus::Ready);
        $this->validator->assertCompleteForOrder($quote);
        self::assertSame([], $this->validator->missingRequiredFields($quote));
        self::assertSame(QuoteStatus::Ready, $quote->getStatus());
    }

    public function testMissingCityStopsConversion(): void
    {
        $quote = $this->completeQuote();
        $quote->setCustomerCity(null);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ort');
        $this->validator->assertCompleteForOrder($quote);
    }

    public function testAllMissingFieldsAreReportedTogether(): void
    {
        $quote = $this->completeQuote();
        $quote->setCustomerPostalCode(' ');
        $quote->setCustomerCity(null);
        try { $this->validator->assertCompleteForOrder($quote); self::fail('Expected validation failure.'); }
        catch (\DomainException $exception) {
            self::assertStringContainsString('Postleitzahl', $exception->getMessage());
            self::assertStringContainsString('Ort', $exception->getMessage());
        }
    }

    public function testMissingCountryStopsConversion(): void
    {
        $quote = $this->completeQuote();
        $quote->setCustomerCountryCode(null);
        $this->expectExceptionMessage('Land');
        $this->validator->assertCompleteForOrder($quote);
    }

    public function testReadyReportsEveryMissingField(): void
    {
        $quote = $this->completeQuote();
        $quote->setCustomerStreet(null);
        $quote->setCustomerPostalCode(null);
        $quote->setCustomerCity(null);
        try { $this->validator->assertCompleteForReady($quote); self::fail('Expected validation failure.'); }
        catch (\DomainException $exception) {
            self::assertStringContainsString('Straße, Postleitzahl, Ort', $exception->getMessage());
            self::assertStringContainsString('noch nicht fertiggestellt', $exception->getMessage());
        }
    }

    public function testAdminTemplateEditsOnlyDraftCustomerData(): void
    {
        $template = (string) file_get_contents(__DIR__.'/../../templates/admin/cardnext/quote/edit.html.twig');
        self::assertStringContainsString("{% if not ready %}<div class=\"row g-3\">", $template);
        self::assertStringContainsString("'customerCity':'Ort'", $template);
        self::assertStringContainsString('form="quote-update"', $template);
        self::assertStringContainsString('{% else %}<strong>{{ quote.customerCompany }}</strong>', $template);
    }

    private function completeQuote(): Quote
    {
        $quote = new Quote();
        $quote->setCustomerContactName('Erika Mustermann');
        $quote->setCustomerEmail('erika@example.com');
        $quote->setCustomerStreet('Musterstraße');
        $quote->setCustomerHouseNumber('1a');
        $quote->setCustomerPostalCode('12345');
        $quote->setCustomerCity('Musterstadt');
        $quote->setCustomerCountryCode('DE');
        return $quote;
    }
}
