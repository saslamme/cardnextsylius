<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteItem;
use App\Enum\Quote\QuoteItemType;

final class QuoteCalculator
{
    public function calculate(Quote $quote): void
    {
        $subtotal = 0;
        $discountTotal = 0;
        $taxBases = [];

        foreach ($quote->getItems() as $item) {
            $this->calculateItem($item);
            $subtotal += $item->getLineTotal();
            $discountTotal += $item->getLineDiscount();
            $rate = $item->getTaxRate() ?? $quote->getDefaultTaxRate();
            $taxBases[$rate] = ($taxBases[$rate] ?? 0) + $item->getLineTotal();
        }

        $quote->setSubtotal($subtotal);
        $quote->setDiscountTotal($discountTotal);
        $taxBases[$quote->getDefaultTaxRate()] = ($taxBases[$quote->getDefaultTaxRate()] ?? 0) + $quote->getShippingTotal() + $quote->getServiceTotal();
        $taxTotal = 0;
        foreach ($taxBases as $rate => $base) {
            $taxTotal += self::taxFor($base, (int) $rate);
        }
        $quote->setTaxTotal($taxTotal);
        $quote->setGrandTotal($subtotal + $quote->getShippingTotal() + $quote->getServiceTotal() + $taxTotal);
    }

    public function calculateItem(QuoteItem $item): void
    {
        if ($item->getItemType() === QuoteItemType::Configured) {
            $subtotal = (int) ($item->getConfiguredSnapshot()['total'] ?? 0);
            $total = $item->getConfiguredLineTotal() ?? $subtotal;
            $discount = max(0, $subtotal - $total);
            $item->setLineSubtotal($subtotal); $item->setLineTotal($total); $item->setLineDiscount($discount); $item->setDiscountAmount($discount);
            $item->setDiscountPercent($subtotal > 0 ? intdiv($discount * 10000 + intdiv($subtotal, 2), $subtotal) : null);
            return;
        }
        $lineTotal = $item->getUnitPrice() * $item->getQuantity();
        $original = $item->getOriginalUnitPrice();
        $lineSubtotal = ($original ?? $item->getUnitPrice()) * $item->getQuantity();
        $lineDiscount = max(0, $lineSubtotal - $lineTotal);

        $item->setLineSubtotal($lineSubtotal);
        $item->setLineDiscount($lineDiscount);
        $item->setDiscountAmount($lineDiscount);
        $item->setLineTotal($lineTotal);
        // Basis points keep 6.44% as 644 without any floating-point arithmetic.
        $item->setDiscountPercent($original !== null && $original > 0
            ? intdiv(max(0, $original - $item->getUnitPrice()) * 10000 + intdiv($original, 2), $original)
            : null);
    }

    public static function taxFor(int $netAmount, int $taxRate): int
    {
        if ($netAmount < 0 || $taxRate < 0) {
            throw new \InvalidArgumentException('Tax basis and rate cannot be negative.');
        }

        return intdiv($netAmount * $taxRate + 5000, 10000);
    }
}
