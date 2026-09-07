<?php

declare(strict_types=1);

namespace App\Service\Support;

final class FreshdeskTicketStatusPresenter
{
    public function translationKey(int $status): string
    {
        return match ($status) {
            2 => 'cardnext.support.status.open',
            3 => 'cardnext.support.status.pending',
            4 => 'cardnext.support.status.resolved',
            5 => 'cardnext.support.status.closed',
            default => 'cardnext.support.status.processing',
        };
    }
}
