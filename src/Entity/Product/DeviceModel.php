<?php

declare(strict_types=1);

namespace App\Entity\Product;

use App\Repository\Product\DeviceModelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: DeviceModelRepository::class)]
#[ORM\Table(name: 'cardnext_device_model')]
#[UniqueEntity(fields: ['code'], message: 'Dieser Gerätemodell-Code ist bereits vergeben.')]
#[UniqueEntity(fields: ['slug'], message: 'Dieser Gerätemodell-Slug ist bereits vergeben.')]
class DeviceModel
{
    public const TYPE_CARD_PRINTER = 'card_printer';

    public const TYPE_RFID_READER = 'rfid_reader';

    public const TYPE_BARCODE_SCANNER = 'barcode_scanner';

    public const TYPE_TIME_TERMINAL = 'time_terminal';

    public const TYPE_ACCESS_CONTROL_DEVICE = 'access_control_device';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_LEGACY = 'legacy';

    public const STATUS_DISCONTINUED = 'discontinued';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $code = '';

    #[ORM\ManyToOne(targetEntity: Manufacturer::class)]
    #[ORM\JoinColumn(name: 'manufacturer_id', nullable: false, onDelete: 'RESTRICT')]
    private Manufacturer $manufacturer;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, unique: true)]
    private string $slug = '';

    #[ORM\Column(name: 'device_type', length: 40)]
    private string $deviceType = self::TYPE_CARD_PRINTER;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'linked_product_id', nullable: true, onDelete: 'SET NULL')]
    private ?Product $linkedProduct = null;

    /** @var Collection<int, DeviceModelAlias> */
    #[ORM\OneToMany(mappedBy: 'deviceModel', targetEntity: DeviceModelAlias::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['alias' => 'ASC'])]
    private Collection $aliases;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->aliases = new ArrayCollection();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = strtoupper(trim($code));
        $this->touch();
    }

    public function getManufacturer(): Manufacturer
    {
        return $this->manufacturer;
    }

    public function setManufacturer(Manufacturer $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
        $this->touch();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
        $this->touch();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = strtolower(trim($slug));
        $this->touch();
    }

    public function getDeviceType(): string
    {
        return $this->deviceType;
    }

    public function setDeviceType(string $deviceType): void
    {
        self::assertChoice($deviceType, self::typeLabels(), 'device type');
        $this->deviceType = $deviceType;
        $this->touch();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        self::assertChoice($status, self::statusLabels(), 'device status');
        $this->status = $status;
        $this->touch();
    }

    public function getLinkedProduct(): ?Product
    {
        return $this->linkedProduct;
    }

    public function setLinkedProduct(?Product $linkedProduct): void
    {
        $this->linkedProduct = $linkedProduct;
        $this->touch();
    }

    /** @return Collection<int, DeviceModelAlias> */
    public function getAliases(): Collection
    {
        return $this->aliases;
    }

    public function addAlias(DeviceModelAlias $alias): void
    {
        if (!$this->aliases->contains($alias)) {
            $this->aliases->add($alias);
            $alias->setDeviceModel($this);
        }
    }

    public function removeAlias(DeviceModelAlias $alias): void
    {
        $this->aliases->removeElement($alias);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [self::TYPE_CARD_PRINTER => 'Kartendrucker', self::TYPE_RFID_READER => 'RFID-Leser', self::TYPE_BARCODE_SCANNER => 'Barcode-Scanner', self::TYPE_TIME_TERMINAL => 'Zeitterminal', self::TYPE_ACCESS_CONTROL_DEVICE => 'Zutrittsgerät'];
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [self::STATUS_ACTIVE => 'Aktiv', self::STATUS_LEGACY => 'Legacy', self::STATUS_DISCONTINUED => 'Eingestellt'];
    }

    public function __toString(): string
    {
        return trim(sprintf('%s %s', isset($this->manufacturer) ? $this->manufacturer->getName() : '', $this->name));
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @param array<string, string> $choices */
    private static function assertChoice(string $value, array $choices, string $field): void
    {
        if (!isset($choices[$value])) {
            throw new \InvalidArgumentException(sprintf('Unsupported %s "%s".', $field, $value));
        }
    }
}
