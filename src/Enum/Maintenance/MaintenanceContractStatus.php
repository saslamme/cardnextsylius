<?php

declare(strict_types=1);

namespace App\Enum\Maintenance;

enum MaintenanceContractStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Expired = 'expired';
}
