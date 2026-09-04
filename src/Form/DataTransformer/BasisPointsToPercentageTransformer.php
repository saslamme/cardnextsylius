<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/** @implements DataTransformerInterface<int|null, string|null> */
final class BasisPointsToPercentageTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return rtrim(rtrim(sprintf('%d,%02d', intdiv($value, 100), $value % 100), '0'), ',');
    }

    public function reverseTransform(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new TransformationFailedException('Bitte einen Prozentwert mit höchstens zwei Nachkommastellen eingeben.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
