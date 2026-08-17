<?php

declare(strict_types=1);

namespace App\Enum\Configurator;

enum DependencyOperator: string
{
    case EQUALS = 'equals';
    case NOT_EQUALS = 'not_equals';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case GREATER_THAN = 'greater_than';
    case GREATER_THAN_OR_EQUAL = 'greater_than_or_equal';
    case LESS_THAN = 'less_than';
    case LESS_THAN_OR_EQUAL = 'less_than_or_equal';
    case IS_SELECTED = 'is_selected';
}
