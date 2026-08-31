<?php

declare(strict_types=1);

namespace App\Integration\Erp\Maintenance;

final readonly class ErpMaintenanceContractData
{
    public function __construct(public string $externalId, public string $erpCustomerNumber, public string $serialNumber, public \DateTimeImmutable $startsAt, public \DateTimeImmutable $endsAt, public ?string $printerModel = null, public ?string $contractReference = null, public ?\DateTimeImmutable $sourceUpdatedAt = null)
    {
    }
}
