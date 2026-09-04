<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/** @implements DataTransformerInterface<int|null, string|null> */
final class MinorUnitsToMoneyTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return sprintf('%d,%02d', intdiv($value, 100), $value % 100);
    }

    public function reverseTransform(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new TransformationFailedException('Bitte einen Geldbetrag mit höchstens zwei Nachkommastellen eingeben.');
        }

        [$euros, $cents] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $euros * 100) + (int) str_pad($cents, 2, '0');
    }
}
