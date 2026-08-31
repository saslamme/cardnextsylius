<?php

declare(strict_types=1);

namespace App\Tests\Calculator;

use App\Calculator\B2BCatalogPriceCalculator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

final class B2BCatalogPriceCalculatorTest extends TestCase
{
    public function testItDecoratesTheSyliusProductVariantPriceCalculator(): void
    {
        $attributes = (new \ReflectionClass(B2BCatalogPriceCalculator::class))->getAttributes(AsDecorator::class);

        self::assertCount(1, $attributes);
        self::assertSame('sylius.calculator.product_variant_price', $attributes[0]->newInstance()->decorates);
    }
}
