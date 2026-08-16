<?php

declare(strict_types=1);

namespace App\LegacyImport;

final class LegacyPriceParser
{
    public function parse(string $value): ?int
    {
        $value = preg_replace('/[^0-9,.-]/', '', trim($value)) ?? '';
        if ($value === '') {
            return null;
        }
        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');
        $decimal = $comma !== false && ($dot === false || $comma > $dot) ? ',' : '.';
        $parts = explode($decimal, $value);
        $fraction = count($parts) > 1 ? array_pop($parts) : '00';
        $whole = preg_replace('/\D/', '', implode('', $parts)) ?? '';
        if ($whole === '') {
            return null;
        }
        $fraction = str_pad(substr(preg_replace('/\D/', '', $fraction) ?? '', 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }
}
