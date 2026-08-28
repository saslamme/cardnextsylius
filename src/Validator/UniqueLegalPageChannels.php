<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueLegalPageChannels extends Constraint
{
    public string $message = 'Für den Verkaufskanal "{{ channel }}" existiert bereits ein Rechtstext dieses Typs.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
