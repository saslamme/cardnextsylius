<?php

declare(strict_types=1);

namespace App\Service\Quote;

final class MinorUnitParser
{
    public function parse(string $value): int
    {
        $normalized = str_replace(["\u{00A0}", ' ', '.'], '', trim($value));
        if (!preg_match('/^\d+(?:,(\d{1,2}))?$/', $normalized, $matches)) {
            throw new \InvalidArgumentException('Invalid monetary amount.');
        }

        return ((int) strtok($normalized, ',')) * 100 + (int) str_pad($matches[1] ?? '', 2, '0');
    }

    public function format(int $minor): string
    {
        return sprintf('%d,%02d', intdiv($minor, 100), $minor % 100);
    }
}
