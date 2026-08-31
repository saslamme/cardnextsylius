<?php

declare(strict_types=1);

namespace App\Integration\Erp\Maintenance;

interface ErpMaintenanceContractProviderInterface
{
    /** @return iterable<ErpMaintenanceContractData> */
    public function fetchAll(): iterable;
}
