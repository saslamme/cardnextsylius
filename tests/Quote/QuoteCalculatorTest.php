<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteItem;
use App\Enum\Quote\QuoteItemType;
use App\Enum\Quote\QuoteStatus;
use App\Service\Quote\MinorUnitParser;
use App\Service\Quote\QuoteCalculator;
use PHPUnit\Framework\TestCase;

final class QuoteCalculatorTest extends TestCase
{
    public function testPriceQuantityDiscountShippingAndServiceUseMinorUnits(): void
    {
        $quote = new Quote();
        $item = new QuoteItem();
        $item->setName('Zebra ZC350 Duplex'); $item->setQuantity(3); $item->setOriginalUnitPrice(114900); $item->setUnitPrice(107500);
        $quote->addItem($item); $quote->setShippingTotal(990); $quote->setServiceTotal(14900);

        (new QuoteCalculator())->calculate($quote);

        self::assertSame(322500, $item->getLineTotal());
        self::assertSame(344700, $item->getLineSubtotal());
        self::assertSame(22200, $item->getLineDiscount());
        self::assertSame(644, $item->getDiscountPercent());
        self::assertSame(338390, $quote->getGrandTotal());
        self::assertSame(0, $quote->getTaxTotal());
    }

    public function testCustomItemNeedsNoProductOrVariant(): void
    {
        $item = new QuoteItem(); $item->setName('Einrichtung Kartendrucksystem'); $item->setItemType(QuoteItemType::Custom); $item->setUnitPrice(14900);
        self::assertNull($item->getProduct()); self::assertNull($item->getVariant());
        self::assertSame(QuoteItemType::Custom, $item->getItemType());
    }

    public function testItemCanBeRemovedWithoutTouchingTheRequestSnapshot(): void
    {
        $quote = new Quote(); $item = new QuoteItem(); $quote->addItem($item); $quote->removeItem($item);
        self::assertCount(0, $quote->getItems());
    }

    public function testDraftCanBecomeReadyOnlyThroughExplicitWorkflow(): void
    {
        $quote = new Quote(); self::assertSame(QuoteStatus::Draft, $quote->getStatus()); $quote->setStatus(QuoteStatus::Ready); self::assertSame(QuoteStatus::Ready, $quote->getStatus());
    }

    public function testLocalizedMoneyParserNeverUsesFloats(): void
    {
        $parser = new MinorUnitParser(); self::assertSame(107500, $parser->parse('1.075,00')); self::assertSame('1075,00', $parser->format(107500));
    }
}
