<?php

declare(strict_types=1);

namespace App\Entity\Pricing;

use App\Entity\Product\ProductVariant;
use App\Repository\Pricing\VariantTierPriceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VariantTierPriceRepository::class)]
#[ORM\Table(name: 'cardnext_variant_tier_price')]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_VARIANT_TIER', columns: ['variant_id', 'channel_code', 'min_quantity'])]
#[ORM\Index(columns: ['variant_id', 'channel_code', 'min_quantity'], name: 'IDX_CN_VARIANT_TIER_LOOKUP')]
class VariantTierPrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(name: 'variant_id', nullable: false, onDelete: 'CASCADE')]
    private ProductVariant $variant;

    #[ORM\Column(name: 'channel_code', length: 255)]
    #[Assert\NotBlank]
    private string $channelCode = '';

    #[ORM\Column(name: 'min_quantity', type: 'integer')]
    #[Assert\GreaterThanOrEqual(1)]
    private int $minQuantity = 1;

    #[ORM\Column(type: 'integer')]
    #[Assert\GreaterThanOrEqual(0)]
    private int $price = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVariant(): ProductVariant { return $this->variant; }
    public function setVariant(ProductVariant $variant): void { $this->variant = $variant; $this->touch(); }
    public function getChannelCode(): string { return $this->channelCode; }
    public function setChannelCode(string $channelCode): void { $this->channelCode = trim($channelCode); $this->touch(); }
    public function getMinQuantity(): int { return $this->minQuantity; }
    public function setMinQuantity(int $minQuantity): void
    {
        if ($minQuantity < 1) { throw new \InvalidArgumentException('Minimum quantity must be at least 1.'); }
        $this->minQuantity = $minQuantity; $this->touch();
    }
    public function getPrice(): int { return $this->price; }
    public function setPrice(int $price): void
    {
        if ($price < 0) { throw new \InvalidArgumentException('Price must not be negative.'); }
        $this->price = $price; $this->touch();
    }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    private function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
