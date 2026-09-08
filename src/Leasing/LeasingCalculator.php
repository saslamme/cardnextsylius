<?php
declare(strict_types=1);
namespace App\Leasing;
final class LeasingCalculator
{
    /** Calculates minor currency units without binary floating point arithmetic. */
    public function calculate(int $netPrice, string $factor): int
    {
        if ($netPrice < 0 || !preg_match('/^\d+(?:[.,]\d{1,8})?$/', $factor)) throw new \InvalidArgumentException('Invalid leasing calculation input.');
        [$whole,$fraction]=array_pad(explode('.',str_replace(',','.',$factor),2),2,'');
        $scaled=((int)$whole*100000000)+(int)str_pad($fraction,8,'0');
        if ($scaled<=0) throw new \InvalidArgumentException('The leasing factor must be positive.');
        return intdiv(($netPrice*$scaled)+50000000,100000000);
    }
}
