<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_product_bundle_item')]
class ProductBundleItem
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column(type: 'integer')] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: ProductBundle::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'bundle_id', nullable: false, onDelete: 'CASCADE')]
    private ProductBundle $bundle;
    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(name: 'variant_id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ProductVariant $variant;
    #[ORM\Column(type: 'integer')] #[Assert\Positive] private int $quantity = 1;
    #[ORM\Column(type: 'integer', options: ['default' => 0])] #[Assert\PositiveOrZero] private int $position = 10;
    #[ORM\Column(options: ['default' => true])] private bool $enabled = true;

    public function getId(): ?int { return $this->id; }
    public function getBundle(): ProductBundle { return $this->bundle; }
    public function setBundle(ProductBundle $bundle): void { $this->bundle = $bundle; }
    public function getVariant(): ProductVariant { return $this->variant; }
    public function hasVariant(): bool { return isset($this->variant); }
    public function setVariant(ProductVariant $variant): void { $this->variant = $variant; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): void { $this->quantity = $quantity; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): void { $this->position = $position; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): void { $this->enabled = $enabled; }
}
