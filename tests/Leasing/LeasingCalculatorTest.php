<?php
declare(strict_types=1);
namespace App\Tests\Leasing;
use App\Leasing\LeasingCalculator; use PHPUnit\Framework\Attributes\DataProvider; use PHPUnit\Framework\TestCase;
final class LeasingCalculatorTest extends TestCase
{
 #[DataProvider('cases')] public function testCalculatesAndRoundsInMinorUnits(int $price,string $factor,int $expected):void{self::assertSame($expected,(new LeasingCalculator())->calculate($price,$factor));}
 public static function cases():iterable{yield [200000,'0.026',5200];yield [169500,'0.02944',4990];yield [1,'0.5',1];yield [10000,'0.00005000',1];}
 public function testRejectsInvalidFactor():void{$this->expectException(\InvalidArgumentException::class);(new LeasingCalculator())->calculate(100,'nope');}
}
