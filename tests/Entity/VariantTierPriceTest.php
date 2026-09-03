<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Pricing\VariantTierPrice;
use PHPUnit\Framework\TestCase;

final class VariantTierPriceTest extends TestCase
{
    public function testRejectsInvalidMinimumQuantity(): void { $this->expectException(\InvalidArgumentException::class); (new VariantTierPrice())->setMinQuantity(0); }
    public function testRejectsNegativePrice(): void { $this->expectException(\InvalidArgumentException::class); (new VariantTierPrice())->setPrice(-1); }
    public function testAcceptsZeroPriceAndPositiveQuantity(): void { $tier = new VariantTierPrice(); $tier->setMinQuantity(1); $tier->setPrice(0); self::assertSame(0, $tier->getPrice()); }
}
