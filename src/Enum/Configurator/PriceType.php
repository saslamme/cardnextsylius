<?php

declare(strict_types=1);

namespace App\Enum\Configurator;

enum PriceType: string
{
    case UNIT = 'unit';
    case FIXED = 'fixed';
    case PERCENT = 'percent';
}
