<?php

declare(strict_types=1);

namespace App\Service\Maintenance;

final readonly class MaintenanceContractSyncResult
{
    public function __construct(public int $fetched, public int $created, public int $updated, public int $unchanged, public int $skipped, public int $errors)
    {
    }
}
