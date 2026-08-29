<?php

declare(strict_types=1);

namespace App\Enum\Quote;

enum QuoteItemType: string
{
    case Product = 'product';
    case Service = 'service';
    case Shipping = 'shipping';
    case Custom = 'custom';
}
