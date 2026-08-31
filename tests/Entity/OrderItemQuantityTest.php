<?php

declare(strict_types=1);

namespace Tests\Entity;

use App\Entity\Order\OrderItem;
use App\Entity\Order\OrderItemUnit;
use App\Entity\Product\ProductVariant;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class OrderItemQuantityTest extends TestCase
{
    public function testItRejectsAQuantityBelowTheVariantMinimum(): void
    {
        $item = $this->itemWithRules(5, 10, 5);

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($item);

        self::assertCount(1, $violations);
        $violation = $violations->get(0);
        self::assertSame('quantity', $violation->getPropertyPath());
        self::assertStringContainsString('10', (string) $violation->getMessage());
    }

    public function testItRejectsAQuantityOutsideTheVariantIncrement(): void
    {
        $item = $this->itemWithRules(11, 10, 5);

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($item);

        self::assertCount(1, $violations);
        $violation = $violations->get(0);
        self::assertStringContainsString('5', (string) $violation->getMessage());
    }

    public function testItAcceptsAValidVariantQuantity(): void
    {
        $item = $this->itemWithRules(15, 10, 5);

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($item);

        self::assertCount(0, $violations);
    }

    private function itemWithRules(int $quantity, int $minimum, int $increment): OrderItem
    {
        $variant = new ProductVariant();
        $variant->setMinimumOrderQuantity($minimum);
        $variant->setOrderIncrement($increment);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(100);
        for ($unit = 0; $unit < $quantity; ++$unit) {
            new OrderItemUnit($item);
        }

        return $item;
    }
}
