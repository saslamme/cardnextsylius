<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class PublicSlugUnique extends Constraint
{
    public string $message = 'The public slug "{{ slug }}" is already used by a {{ type }} in locale "{{ locale }}".';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
