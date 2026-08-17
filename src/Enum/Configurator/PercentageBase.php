<?php

declare(strict_types=1);

namespace App\Enum\Configurator;

enum PercentageBase: string
{
    case BASE = 'base';
    case OPTIONS = 'options';
    case SUBTOTAL = 'subtotal';
}
