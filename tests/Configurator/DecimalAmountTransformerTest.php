<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Service\Configurator\Admin\DecimalAmountTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecimalAmountTransformerTest extends TestCase
{
    #[DataProvider('money')]
    public function testMoney(string $input, string $currency, int $expected): void
    {
        self::assertSame($expected, (new DecimalAmountTransformer())->toMinorUnits($input, $currency));
    }

    public static function money(): iterable
    {
        yield ['0.89', 'EUR', 89];
        yield ['29,00', 'EUR', 2900];
        yield ['125.50', 'EUR', 12550];
        yield ['100', 'JPY', 100];
        yield ['1.234', 'KWD', 1234];
    }

    #[DataProvider('percentages')]
    public function testPercentages(string $input, int $expected): void
    {
        self::assertSame($expected, (new DecimalAmountTransformer())->percentageToBasisPoints($input));
    }

    public static function percentages(): iterable
    {
        yield ['20', 2000];
        yield ['12,5', 1250];
        yield ['2.75', 275];
    }
}
