<?php

declare(strict_types=1);

namespace App\Entity\Maintenance;

use App\Entity\Customer\Customer;
use App\Enum\Maintenance\MaintenanceContractStatus;
use App\Repository\Maintenance\MaintenanceContractRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaintenanceContractRepository::class)]
#[ORM\Table(name: 'cardnext_maintenance_contract')]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_MAINTENANCE_ERP_ID', columns: ['erp_contract_id'])]
#[ORM\Index(columns: ['erp_customer_number'], name: 'IDX_CN_MAINTENANCE_ERP_CUSTOMER')]
#[ORM\Index(columns: ['ends_at'], name: 'IDX_CN_MAINTENANCE_ENDS')]
class MaintenanceContract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false)]
    private Customer $customer;

    #[ORM\Column(name: 'erp_contract_id', length: 128)]
    private string $erpContractId;

    #[ORM\Column(name: 'erp_customer_number', length: 64)]
    private string $erpCustomerNumber;

    /** @var Collection<int, MaintenanceContractDevice> */
    #[ORM\OneToMany(mappedBy: 'contract', targetEntity: MaintenanceContractDevice::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $devices;

    #[ORM\Column(name: 'printer_model', length: 255, nullable: true)]
    private ?string $printerModel = null;

    #[ORM\Column(name: 'contract_reference', length: 255, nullable: true)]
    private ?string $contractReference = null;

    #[ORM\Column(name: 'starts_at', type: 'date_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(name: 'ends_at', type: 'date_immutable')]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(name: 'last_synced_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastSyncedAt;

    #[ORM\Column(name: 'source_updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sourceUpdatedAt = null;

    #[ORM\Column(name: 'internal_note', type: 'text', nullable: true)]
    private ?string $internalNote = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $erpContractId, Customer $customer, string $erpCustomerNumber, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, \DateTimeImmutable $syncedAt)
    {
        $this->devices = new ArrayCollection();
        $this->createdAt = $syncedAt;
        $this->applyErpData($customer, $erpContractId, $erpCustomerNumber, $startsAt, $endsAt, null, null, null, $syncedAt);
    }

    public function applyErpData(Customer $customer, string $erpContractId, string $erpCustomerNumber, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, ?string $printerModel, ?string $contractReference, ?\DateTimeImmutable $sourceUpdatedAt, \DateTimeImmutable $syncedAt): void
    {
        $erpContractId = trim($erpContractId);
        $erpCustomerNumber = trim($erpCustomerNumber);
        if ($erpContractId === '' || $erpCustomerNumber === '') {
            throw new \InvalidArgumentException('ERP identity and customer number are required.');
        }
        if ($endsAt < $startsAt) {
            throw new \InvalidArgumentException('Maintenance contract end date must not precede its start date.');
        }
        $this->customer = $customer;
        $this->erpContractId = $erpContractId;
        $this->erpCustomerNumber = $erpCustomerNumber;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->printerModel = self::optional($printerModel);
        $this->contractReference = self::optional($contractReference);
        $this->sourceUpdatedAt = $sourceUpdatedAt;
        $this->lastSyncedAt = $syncedAt;
        $this->updatedAt = $syncedAt;
    }

    public function statusAt(\DateTimeImmutable $today): MaintenanceContractStatus
    {
        $today = $today->setTime(0, 0);
        if ($this->startsAt > $today) {
            return MaintenanceContractStatus::Upcoming;
        }
        if ($this->endsAt < $today) {
            return MaintenanceContractStatus::Expired;
        }

        return MaintenanceContractStatus::Active;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getErpContractId(): string
    {
        return $this->erpContractId;
    }

    public function getErpCustomerNumber(): string
    {
        return $this->erpCustomerNumber;
    }

    /** @return Collection<int, MaintenanceContractDevice> */
    public function getDevices(): Collection
    {
        return $this->devices;
    }

    public function addDevice(MaintenanceContractDevice $device): void
    {
        if ($this->hasSerialNumber($device->getSerialNumber())) {
            return;
        }

        $this->devices->add($device);
        $device->assignTo($this);
    }

    public function removeDevice(MaintenanceContractDevice $device): void
    {
        if ($this->devices->removeElement($device)) {
            $device->detachFrom($this);
        }
    }

    /** @return list<string> */
    public function getSerialNumbers(): array
    {
        return array_values($this->devices->map(static fn (MaintenanceContractDevice $device): string => $device->getSerialNumber())->toArray());
    }

    public function hasSerialNumber(string $serialNumber): bool
    {
        $serialNumber = trim($serialNumber);

        return $serialNumber !== '' && $this->devices->exists(static fn (int $key, MaintenanceContractDevice $device): bool => $device->getSerialNumber() === $serialNumber);
    }

    public function getPrinterModel(): ?string
    {
        return $this->printerModel;
    }

    public function getContractReference(): ?string
    {
        return $this->contractReference;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getLastSyncedAt(): \DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function getSourceUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->sourceUpdatedAt;
    }

    public function getInternalNote(): ?string
    {
        return $this->internalNote;
    }

    public function setInternalNote(?string $note): void
    {
        $this->internalNote = self::optional($note);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function optional(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
