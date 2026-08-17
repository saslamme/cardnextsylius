<?php

declare(strict_types=1);

namespace App\Enum\Configurator;

enum DependencyEffect: string
{
    case SHOW = 'show';
    case HIDE = 'hide';
    case ENABLE = 'enable';
    case DISABLE = 'disable';
    case REQUIRE = 'require';
    case FORBID = 'forbid';
}
