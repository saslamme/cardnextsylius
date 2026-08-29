<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteItem;
use App\Service\Quote\QuoteCalculator;
use App\Service\Quote\QuoteIssuerProfileRegistry;
use App\Service\Quote\QuotePdfRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;

final class QuotePdfRendererTest extends TestCase
{
    public function testTemplateUsesCompleteSnapshotAndConfiguredIssuerWithoutEmptyLabels(): void
    {
        $quote = $this->quote(1900);
        $quote->setInternalNote('DARF-NICHT-IM-PDF-STEHEN');

        $html = $this->twig()->render('pdf/quote.html.twig', [
            'quote' => $quote,
            'issuer' => $this->issuer(),
            'taxes' => [1900 => 1900],
            'hasZeroTax' => false,
        ]);

        self::assertStringContainsString('ANGEBOT', $html);
        self::assertStringContainsString('Angebotsnummer: <strong>AN-2026-0042</strong>', $html);
        self::assertStringContainsString('Version: 3', $html);
        self::assertStringContainsString('Snapshot GmbH', $html);
        self::assertStringContainsString('Erika Muster', $html);
        self::assertStringContainsString('Kundenweg', $html);
        self::assertStringContainsString('12a', $html);
        self::assertStringContainsString('50667', $html);
        self::assertStringContainsString('Köln', $html);
        self::assertStringContainsString('DE', $html);
        self::assertStringContainsString('Aussteller GmbH', $html);
        self::assertStringContainsString('DE123456789', $html);
        self::assertStringContainsString('MwSt. 19,00 %', $html);
        self::assertStringNotContainsString('DARF-NICHT-IM-PDF-STEHEN', $html);
        self::assertStringNotContainsString('Geschäftsführung:', $html);
        self::assertStringNotContainsString('IBAN ', $html);
    }

    public function testZeroTaxNoteIsRenderedOnlyForZeroTax(): void
    {
        $quote = $this->quote(0);
        $quote->setTaxNote('Gespeicherter individueller Steuerhinweis.');
        $twig = $this->twig();
        $context = ['quote' => $quote, 'issuer' => $this->issuer(), 'taxes' => [0 => 0]];

        $zeroTaxHtml = $twig->render('pdf/quote.html.twig', $context + ['hasZeroTax' => true]);
        $taxedHtml = $twig->render('pdf/quote.html.twig', $context + ['hasZeroTax' => false]);

        self::assertStringContainsString('Gespeicherter individueller Steuerhinweis.', $zeroTaxHtml);
        self::assertStringNotContainsString('Gespeicherter individueller Steuerhinweis.', $taxedHtml);
    }

    public function testPdfWithManyItemsIsSuccessfullyGenerated(): void
    {
        $quote = $this->quote(1900, 16);

        $renderer = new QuotePdfRenderer(
            $this->twig(),
            new QuoteIssuerProfileRegistry(['CARDNEXT_DE' => $this->issuer()]),
        );
        $pdf = $renderer->render($quote);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(10_000, strlen($pdf));
    }

    public function testIssuerRegistryProvidesEverySupportedFieldAndRejectsTypos(): void
    {
        $registry = new QuoteIssuerProfileRegistry(['CHANNEL' => ['company' => 'Configured GmbH']]);
        $profile = $registry->get('CHANNEL');

        self::assertSame('Configured GmbH', $profile['company']);
        self::assertArrayHasKey('bic', $profile);
        self::assertNull($profile['bic']);

        $this->expectException(\InvalidArgumentException::class);
        new QuoteIssuerProfileRegistry(['CHANNEL' => ['companyName' => 'Typo']]);
    }

    private function quote(int $taxRate, int $itemCount = 1): Quote
    {
        $quote = new Quote();
        $quote->setNumber('AN-2026-0042');
        $quote->setVersion(3);
        $quote->setChannelCode('CARDNEXT_DE');
        $quote->setLocaleCode('de_DE');
        $quote->setCurrencyCode('EUR');
        $quote->setQuoteDate(new \DateTimeImmutable('2026-08-29'));
        $quote->setValidUntil(new \DateTimeImmutable('2026-09-13'));
        $quote->setCustomerCompany('Snapshot GmbH');
        $quote->setCustomerContactName('Erika Muster');
        $quote->setCustomerStreet('Kundenweg');
        $quote->setCustomerHouseNumber('12a');
        $quote->setCustomerPostalCode('50667');
        $quote->setCustomerCity('Köln');
        $quote->setCustomerCountryCode('DE');
        $quote->setCustomerNumber('K-4711');
        $quote->setProjectReference('Rollout 2026');
        $quote->setCustomerPurchaseOrderNumber('PO-99');
        $quote->setPaymentTerms('14 Tage netto');
        $quote->setDeliveryTerms('Lieferung frei Haus');
        $quote->setCustomerNote('Nur gespeicherter Kundenhinweis');
        $quote->setDefaultTaxRate($taxRate);

        for ($position = 1; $position <= $itemCount; ++$position) {
            $item = new QuoteItem();
            $item->setPosition($position);
            $item->setName(sprintf('Position %02d mit ausführlicher Beschreibung', $position));
            $item->setProductCode('SEHR-LANGE-ARTIKELNUMMER-2026-'.$position);
            $item->setVariantCode('VAR-'.$position);
            $item->setDescription(str_repeat('Mehrzeiliger Beschreibungstext. ', 3));
            $item->setQuantity(2);
            $item->setUnitPrice(5_000);
            $quote->addItem($item);
        }

        (new QuoteCalculator())->calculate($quote);

        return $quote;
    }

    /** @return array<string, string|null> */
    private function issuer(): array
    {
        return (new QuoteIssuerProfileRegistry(['TEST' => [
            'company' => 'Aussteller GmbH',
            'street' => 'Anbieterstraße 1',
            'postalCode' => '10115',
            'city' => 'Berlin',
            'country' => 'Deutschland',
            'phone' => '+49 30 123456',
            'email' => 'angebot@example.test',
            'website' => 'example.test',
            'vatId' => 'DE123456789',
            'registrationCourt' => 'Amtsgericht Berlin',
            'registrationNumber' => 'HRB 12345',
        ]]))->get('TEST');
    }

    private function twig(): Environment
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'));
        $twig->addExtension(new IntlExtension());

        return $twig;
    }
}
