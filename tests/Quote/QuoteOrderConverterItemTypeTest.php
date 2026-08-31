<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteItem;
use App\Enum\Quote\QuoteItemType;
use App\Enum\Quote\QuoteStatus;
use App\Service\Quote\QuoteOrderConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuoteOrderConverterItemTypeTest extends TestCase
{
    public function testProductWithoutVariantIsRejected(): void
    {
        $quote = $this->validQuoteWithItem(QuoteItemType::Product);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Die Produktvariante der Position „Testposition“ existiert nicht mehr.');

        $this->validate($quote);
    }

    public function testCustomItemWithoutVariantIsAllowed(): void
    {
        $quote = $this->validQuoteWithItem(QuoteItemType::Custom);

        $this->validate($quote);

        $item = $quote->getItems()->first();
        self::assertInstanceOf(QuoteItem::class, $item);
        self::assertSame(QuoteItemType::Custom, $item->getItemType());
        self::assertNull($item->getVariant());
    }

    /** @return iterable<string, array{QuoteItemType, string}> */
    public static function unsupportedItemTypes(): iterable
    {
        yield 'service' => [QuoteItemType::Service, 'Serviceleistungen werden über den Servicebetrag des Angebots abgebildet'];
        yield 'shipping' => [QuoteItemType::Shipping, 'Versandkosten werden über den Versandbetrag des Angebots abgebildet'];
    }

    #[DataProvider('unsupportedItemTypes')]
    public function testQuoteLevelTotalAndMatchingItemAreRejectedToPreventDoubleCounting(QuoteItemType $type, string $message): void
    {
        $quote = $this->validQuoteWithItem($type);
        $quote->setServiceTotal(1000);
        $quote->setShippingTotal(1000);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage($message);

        $this->validate($quote);
    }

    public function testEveryCurrentItemTypeHasAnExplicitValidationAndConversionArm(): void
    {
        $source = (string) file_get_contents(__DIR__.'/../../src/Service/Quote/QuoteOrderConverter.php');

        foreach (QuoteItemType::cases() as $type) {
            self::assertGreaterThanOrEqual(2, substr_count($source, 'QuoteItemType::'.$type->name));
        }
        self::assertStringContainsString('QuoteItemType::Product => $this->addProductItem(', $source);
        self::assertStringContainsString('QuoteItemType::Custom => $this->addCustomItem(', $source);
        self::assertStringNotContainsString("if (\$quoteItem->getItemType() === QuoteItemType::Custom)", $source);
    }

    private function validQuoteWithItem(QuoteItemType $type): Quote
    {
        $quote = new Quote();
        $quote->setStatus(QuoteStatus::Accepted);
        $quote->setChannelCode('WEB');
        $quote->setCurrencyCode('EUR');
        $quote->setLocaleCode('de_DE');
        $quote->setCustomerContactName('Sascha Lammers');
        $quote->setCustomerEmail('sascha@example.com');
        $quote->setCustomerStreet('Musterstraße');
        $quote->setCustomerHouseNumber('1');
        $quote->setCustomerPostalCode('12345');
        $quote->setCustomerCity('Berlin');
        $quote->setCustomerCountryCode('DE');

        $item = new QuoteItem();
        $item->setName('Testposition');
        $item->setItemType($type);
        $item->setLineTotal(1000);
        $quote->addItem($item);

        return $quote;
    }

    private function validate(Quote $quote): void
    {
        $converter = (new \ReflectionClass(QuoteOrderConverter::class))->newInstanceWithoutConstructor();
        $validatorProperty = new \ReflectionProperty(QuoteOrderConverter::class, 'orderDataValidator');
        $validatorProperty->setValue($converter, new \App\Service\Quote\QuoteOrderDataValidator());

        (new \ReflectionMethod(QuoteOrderConverter::class, 'validate'))->invoke($converter, $quote);
    }
}
