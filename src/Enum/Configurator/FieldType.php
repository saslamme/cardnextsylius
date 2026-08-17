<?php

declare(strict_types=1);

namespace App\Enum\Configurator;

enum FieldType: string
{
    case SINGLE_CHOICE = 'single_choice';
    case MULTIPLE_CHOICE = 'multiple_choice';
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';
    case TEXT = 'text';
    case QUANTITY = 'quantity';
    case UPLOAD = 'upload';
}
