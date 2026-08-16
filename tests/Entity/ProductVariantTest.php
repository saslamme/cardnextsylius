<?php

declare(strict_types=1);

namespace Tests\Entity;

use App\Entity\Product\ProductVariant;
use PHPUnit\Framework\TestCase;

final class ProductVariantTest extends TestCase
{
    public function testItNormalizesVariantIdentifiers(): void
    {
        $variant = new ProductVariant();

        $variant->setManufacturerPartNumber(' AB-12 / 34 ');
        $variant->setGtin(' 400-123 456 ');

        self::assertSame('AB-12 / 34', $variant->getManufacturerPartNumber());
        self::assertSame('ab1234', $variant->getManufacturerPartNumberNormalized());
        self::assertSame('400-123 456', $variant->getGtin());
        self::assertSame('400123456', $variant->getGtinNormalized());
    }

    public function testItValidatesMinimumQuantityAndIncrementFromTheMinimum(): void
    {
        $variant = new ProductVariant();
        $variant->setMinimumOrderQuantity(10);
        $variant->setOrderIncrement(5);

        self::assertFalse($variant->isValidOrderQuantity(9));
        self::assertTrue($variant->isValidOrderQuantity(10));
        self::assertFalse($variant->isValidOrderQuantity(11));
        self::assertTrue($variant->isValidOrderQuantity(15));
    }
}
