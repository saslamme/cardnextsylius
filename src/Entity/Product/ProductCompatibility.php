<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_product_compatibility')]
#[ORM\UniqueConstraint(
    name: 'UNIQ_CARDNEXT_PRODUCT_COMPATIBILITY',
    columns: ['source_product_id', 'target_product_id', 'relation_type'],
)]
#[ORM\Index(columns: ['source_product_id', 'enabled', 'position'], name: 'IDX_CN_COMPAT_SOURCE')]
#[ORM\Index(columns: ['target_product_id', 'enabled', 'position'], name: 'IDX_CN_COMPAT_TARGET')]
class ProductCompatibility
{
    public const TYPE_COMPATIBLE_WITH = 'compatible_with';
    public const TYPE_CONSUMABLE_FOR = 'consumable_for';
    public const TYPE_ACCESSORY_FOR = 'accessory_for';
    public const TYPE_ALTERNATIVE_TO = 'alternative_to';
    public const TYPE_REPLACEMENT_FOR = 'replacement_for';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'compatibilities')]
    #[ORM\JoinColumn(name: 'source_product_id', nullable: false, onDelete: 'CASCADE')]
    private Product $sourceProduct;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'reverseCompatibilities')]
    #[ORM\JoinColumn(name: 'target_product_id', nullable: false, onDelete: 'CASCADE')]
    private Product $targetProduct;

    #[ORM\Column(name: 'relation_type', length: 40)]
    private string $relationType = self::TYPE_COMPATIBLE_WITH;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourceProduct(): Product
    {
        return $this->sourceProduct;
    }

    public function setSourceProduct(Product $sourceProduct): void
    {
        $this->sourceProduct = $sourceProduct;
        $this->touch();
    }

    public function getTargetProduct(): Product
    {
        return $this->targetProduct;
    }

    public function setTargetProduct(Product $targetProduct): void
    {
        $this->targetProduct = $targetProduct;
        $this->touch();
    }

    public function getRelationType(): string
    {
        return $this->relationType;
    }

    public function setRelationType(string $relationType): void
    {
        if (!array_key_exists($relationType, self::typeLabels())) {
            throw new \InvalidArgumentException(sprintf('Unsupported compatibility type "%s".', $relationType));
        }

        $this->relationType = $relationType;
        $this->touch();
    }

    public function getTypeLabel(): string
    {
        return self::typeLabels()[$this->relationType] ?? 'Kompatibel';
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_COMPATIBLE_WITH => 'Kompatibel',
            self::TYPE_CONSUMABLE_FOR => 'Verbrauchsmaterial',
            self::TYPE_ACCESSORY_FOR => 'Zubehör',
            self::TYPE_ALTERNATIVE_TO => 'Alternative',
            self::TYPE_REPLACEMENT_FOR => 'Ersatzprodukt',
        ];
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $note = $note !== null ? trim($note) : null;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCounterpart(Product $product): Product
    {
        if ($this->sourceProduct === $product) {
            return $this->targetProduct;
        }

        if ($this->targetProduct === $product) {
            return $this->sourceProduct;
        }

        throw new \InvalidArgumentException('The supplied product is not part of this compatibility relation.');
    }

    private function touch(): void
    {
        // @phpstan-ignore isset.initializedProperty
        if (isset($this->createdAt)) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }
}
