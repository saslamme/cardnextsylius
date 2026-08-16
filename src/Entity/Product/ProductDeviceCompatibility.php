<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_product_device_compatibility')]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_PRODUCT_DEVICE_COMPAT', columns: ['product_id', 'device_model_id', 'compatibility_type'])]
#[ORM\Index(columns: ['product_id', 'enabled', 'position'], name: 'IDX_CN_PRODUCT_DEVICE_LOOKUP')]
#[ORM\Index(columns: ['device_model_id', 'enabled'], name: 'IDX_CN_DEVICE_PRODUCT_LOOKUP')]
class ProductDeviceCompatibility
{
    public const TYPE_COMPATIBLE_WITH = 'compatible_with';

    public const TYPE_CONSUMABLE_FOR = 'consumable_for';

    public const TYPE_ACCESSORY_FOR = 'accessory_for';

    public const TYPE_CLEANING_FOR = 'cleaning_for';

    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'deviceCompatibilities')]
    #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: DeviceModel::class)]
    #[ORM\JoinColumn(name: 'device_model_id', nullable: false, onDelete: 'CASCADE')]
    private DeviceModel $deviceModel;

    #[ORM\Column(name: 'compatibility_type', length: 40)]
    private string $compatibilityType = self::TYPE_COMPATIBLE_WITH;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $verified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
        $this->touch();
    }

    public function getDeviceModel(): DeviceModel
    {
        return $this->deviceModel;
    }

    public function setDeviceModel(DeviceModel $deviceModel): void
    {
        $this->deviceModel = $deviceModel;
        $this->touch();
    }

    public function getCompatibilityType(): string
    {
        return $this->compatibilityType;
    }

    public function setCompatibilityType(string $type): void
    {
        if (!isset(self::typeLabels()[$type])) {
            throw new \InvalidArgumentException(sprintf('Unsupported device compatibility type "%s".', $type));
        }

        $this->compatibilityType = $type;
        $this->touch();
    }

    public function getTypeLabel(): string
    {
        return self::typeLabels()[$this->compatibilityType];
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [self::TYPE_COMPATIBLE_WITH => 'Kompatibel mit', self::TYPE_CONSUMABLE_FOR => 'Verbrauchsmaterial für', self::TYPE_ACCESSORY_FOR => 'Zubehör für', self::TYPE_CLEANING_FOR => 'Reinigung für'];
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): void
    {
        $this->verified = $verified;
        $this->touch();
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $note = trim((string) $note);
        $this->note = $note !== '' ? $note : null;
        $this->touch();
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
        $this->touch();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
