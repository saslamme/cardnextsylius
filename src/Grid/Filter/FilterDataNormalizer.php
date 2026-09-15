<?php

declare(strict_types=1);

namespace App\Grid\Filter;

final class FilterDataNormalizer
{
    /** @return list<scalar> */
    public static function values(mixed $data): array
    {
        if (is_array($data) && array_key_exists('value', $data)) {
            $data = $data['value'];
        }

        if (!is_array($data)) {
            $data = [$data];
        }

        return array_values(array_filter(
            $data,
            static fn (mixed $value): bool => is_scalar($value) && $value !== '',
        ));
    }
}
