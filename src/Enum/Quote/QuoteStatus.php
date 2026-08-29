<?php

declare(strict_types=1);

namespace App\Enum\Quote;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
}
