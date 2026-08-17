<?php

declare(strict_types=1);

namespace App\Enum\Product;

enum ProductKind: string
{
    case STANDARD = 'standard';
    case CONFIGURABLE = 'configurable';
}
