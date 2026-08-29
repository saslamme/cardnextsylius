<?php

declare(strict_types=1);

namespace App\Enum\Quote;

enum QuoteRequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Question = 'question';
    case Closed = 'closed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::New => in_array($next, [self::InProgress, self::Closed], true),
            self::InProgress => in_array($next, [self::Question, self::Closed], true),
            self::Question => in_array($next, [self::InProgress, self::Closed], true),
            self::Closed => false,
        };
    }
}
