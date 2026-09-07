<?php

declare(strict_types=1);

namespace App\Entity\Maintenance;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_maintenance_contract_device')]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_MAINTENANCE_DEVICE', columns: ['contract_id', 'serial_number'])]
#[ORM\Index(columns: ['serial_number'], name: 'IDX_CN_MAINTENANCE_DEVICE_SERIAL')]
class MaintenanceContractDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MaintenanceContract::class, inversedBy: 'devices')]
    #[ORM\JoinColumn(name: 'contract_id', nullable: false, onDelete: 'CASCADE')]
    private MaintenanceContract $contract;

    #[ORM\Column(name: 'serial_number', length: 255)]
    private string $serialNumber;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(MaintenanceContract $contract, string $serialNumber, \DateTimeImmutable $now)
    {
        $serialNumber = trim($serialNumber);
        if ($serialNumber === '') {
            throw new \InvalidArgumentException('A maintenance-contract device serial number is required.');
        }

        $this->serialNumber = $serialNumber;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $contract->addDevice($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContract(): MaintenanceContract
    {
        return $this->contract;
    }

    public function getSerialNumber(): string
    {
        return $this->serialNumber;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function assignTo(MaintenanceContract $contract): void
    {
        if (isset($this->contract) && $this->contract !== $contract) {
            throw new \LogicException('A maintenance-contract device cannot be moved to another contract.');
        }

        $this->contract = $contract;
    }

    public function detachFrom(MaintenanceContract $contract): void
    {
        if ($this->contract === $contract) {
            unset($this->contract);
        }
    }
}
