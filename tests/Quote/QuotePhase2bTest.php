<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteItem;
use App\Enum\Quote\QuoteStatus;
use App\Service\Quote\QuoteCalculator;
use App\Service\Quote\QuoteDraftGuard;
use App\Service\Quote\QuoteTaxRateResolver;
use PHPUnit\Framework\TestCase;

final class QuotePhase2bTest extends TestCase
{
    private function quote(int $rate, int $net): Quote
    {
        $q = new Quote();
        $q->setDefaultTaxRate($rate);
        $i = new QuoteItem();
        $i->setName('Position');
        $i->setUnitPrice($net);
        $q->addItem($i);
        (new QuoteCalculator())->calculate($q);

        return $q;
    }

    public function testChannelDefaults(): void
    {
        $r = new QuoteTaxRateResolver();
        self::assertSame(1900, $r->resolve('CARDNEXT_DE'));
        self::assertSame(2000, $r->resolve('CARDNEXT_AT'));
        self::assertSame(0, $r->resolve('OTHER'));
    }

    public function testNineteenTwentyZeroAndRounding(): void
    {
        self::assertSame(1900, $this->quote(1900, 10000)->getTaxTotal());
        self::assertSame(2000, $this->quote(2000, 10000)->getTaxTotal());
        self::assertSame(0, $this->quote(0, 10000)->getTaxTotal());
        self::assertSame(1, $this->quote(1900, 3)->getTaxTotal());
    }

    public function testMixedRatesShippingServiceAndDiscount(): void
    {
        $q = $this->quote(1900, 8000);
        $item = $q->getItems()->first();
        self::assertNotFalse($item);
        $item->setOriginalUnitPrice(10000);
        $other = new QuoteItem();
        $other->setName('Reduced');
        $other->setUnitPrice(10000);
        $other->setTaxRate(700);
        $q->addItem($other);
        $q->setShippingTotal(1000);
        $q->setServiceTotal(2000);
        (new QuoteCalculator())->calculate($q);
        self::assertSame(2000, $q->getDiscountTotal());
        self::assertSame(2790, $q->getTaxTotal());
        self::assertSame(23790, $q->getGrandTotal());
    }

    public function testReadyGuard(): void
    {
        $q = new Quote();
        $q->setStatus(QuoteStatus::Ready);
        $this->expectException(\DomainException::class);
        (new QuoteDraftGuard())->assertDraft($q);
    }
}
