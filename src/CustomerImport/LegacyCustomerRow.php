<?php

declare(strict_types=1);

namespace App\CustomerImport;

final readonly class LegacyCustomerRow
{
    /** @param list<string> $columns */
    public function __construct(public int $number, private array $columns)
    {
    }

    public function get(int $column): string
    {
        return trim($this->columns[$column] ?? '');
    }
}
