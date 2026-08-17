<?php

declare(strict_types=1);

namespace App\Service\Configurator\Admin;

final class DecimalAmountTransformer
{
    /** @var array<string, int> */
    private const FRACTION_DIGITS = ['BHD' => 3, 'CLP' => 0, 'ISK' => 0, 'JPY' => 0, 'JOD' => 3, 'KRW' => 0, 'KWD' => 3, 'OMR' => 3, 'TND' => 3];

    public function toMinorUnits(string $amount, string $currency): int
    {
        return $this->parse($amount, self::FRACTION_DIGITS[strtoupper($currency)] ?? 2);
    }

    public function fromMinorUnits(int $amount, string $currency): string
    {
        return $this->format($amount, self::FRACTION_DIGITS[strtoupper($currency)] ?? 2);
    }

    public function percentageToBasisPoints(string $percentage): int
    {
        return $this->parse($percentage, 2);
    }

    public function basisPointsToPercentage(int $basisPoints): string
    {
        return $this->format($basisPoints, 2);
    }

    private function parse(string $value, int $digits): int
    {
        $value = str_replace(["\u{00A0}", ' '], '', trim($value));
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.') ? str_replace('.', '', $value) : str_replace(',', '', $value);
        }
        $value = str_replace(',', '.', $value);
        if (preg_match('/^(\d+)(?:\.(\d+))?$/D', $value, $match) !== 1) {
            throw new \InvalidArgumentException('Bitte einen nicht-negativen Dezimalbetrag eingeben.');
        }
        $fraction = $match[2] ?? '';
        if (strlen($fraction) > $digits) {
            throw new \InvalidArgumentException(sprintf('Für diese Eingabe sind höchstens %d Nachkommastellen erlaubt.', $digits));
        }
        $factor = 10 ** $digits;
        $major = (int) $match[1];
        if ($major > intdiv(\PHP_INT_MAX, $factor)) {
            throw new \InvalidArgumentException('Der Betrag ist zu groß.');
        }

        return ($major * $factor) + (int) str_pad($fraction, $digits, '0');
    }

    private function format(int $value, int $digits): string
    {
        if ($digits === 0) {
            return (string) $value;
        }
        $factor = 10 ** $digits;

        return sprintf('%d,%0' . $digits . 'd', intdiv($value, $factor), $value % $factor);
    }
}
