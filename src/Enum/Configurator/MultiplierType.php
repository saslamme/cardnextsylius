<?php

declare(strict_types=1);

namespace App\Enum\Configurator;

enum MultiplierType: string
{
    case NONE = 'none';
    case QUANTITY = 'quantity';
    case FIELD_VALUE = 'field_value';
}
