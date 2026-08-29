<?php

declare(strict_types=1);

namespace App\Enum\Quote;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => $next === self::Ready,
            self::Ready => in_array($next, [self::Sent, self::Superseded], true),
            self::Sent => in_array($next, [self::Sent, self::Accepted, self::Rejected, self::Superseded], true),
            self::Rejected => $next === self::Superseded,
            self::Accepted, self::Superseded => false,
        };
    }
}
