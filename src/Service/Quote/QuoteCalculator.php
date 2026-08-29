<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteItem;

final class QuoteCalculator
{
    public function calculate(Quote $quote): void
    {
        $subtotal = 0;
        $discountTotal = 0;

        foreach ($quote->getItems() as $item) {
            $this->calculateItem($item);
            $subtotal += $item->getLineTotal();
            $discountTotal += $item->getLineDiscount();
        }

        $quote->setSubtotal($subtotal);
        $quote->setDiscountTotal($discountTotal);
        // Phase 2a is deliberately net-only. Tax calculation requires a concrete
        // customer zone/tax category context and will be integrated in Phase 2b.
        $quote->setTaxTotal(0);
        $quote->setGrandTotal($subtotal + $quote->getShippingTotal() + $quote->getServiceTotal());
    }

    public function calculateItem(QuoteItem $item): void
    {
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
}
