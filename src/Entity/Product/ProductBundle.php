<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_product_bundle')]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_BUNDLE_CODE', columns: ['code'])]
class ProductBundle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9_-]*$/')]
    private string $code = '';

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'bundles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Product $mainProduct;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProductBundleItem> */
    #[ORM\OneToMany(mappedBy: 'bundle', targetEntity: ProductBundleItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Count(min: 1, minMessage: 'Ein Bundle benötigt mindestens einen Bestandteil.')]
    #[Assert\Valid]
    private Collection $items;

    /** @var Collection<int, ProductBundleChannel> */
    #[ORM\OneToMany(mappedBy: 'bundle', targetEntity: ProductBundleChannel::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Count(min: 1, minMessage: 'Ein Bundle benötigt mindestens eine Channel-Konfiguration.')]
    #[Assert\Valid]
    private Collection $channelConfigurations;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->channelConfigurations = new ArrayCollection();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): void { $this->code = trim($code); $this->touch(); }
    public function getMainProduct(): Product { return $this->mainProduct; }
    public function setMainProduct(Product $product): void { $this->mainProduct = $product; $this->touch(); }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = trim($name); $this->touch(); }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): void { $this->enabled = $enabled; $this->touch(); }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): void { $this->position = $position; $this->touch(); }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    /** @return Collection<int, ProductBundleItem> */ public function getItems(): Collection { return $this->items; }
    public function addItem(ProductBundleItem $item): void { if (!$this->items->contains($item)) { $this->items->add($item); $item->setBundle($this); $this->touch(); } }
    public function removeItem(ProductBundleItem $item): void { $this->items->removeElement($item); $this->touch(); }
    /** @return Collection<int, ProductBundleChannel> */ public function getChannelConfigurations(): Collection { return $this->channelConfigurations; }
    public function addChannelConfiguration(ProductBundleChannel $configuration): void { if (!$this->channelConfigurations->contains($configuration)) { $this->channelConfigurations->add($configuration); $configuration->setBundle($this); $this->touch(); } }
    public function removeChannelConfiguration(ProductBundleChannel $configuration): void { $this->channelConfigurations->removeElement($configuration); $this->touch(); }
    public function configurationFor(string $channelCode): ?ProductBundleChannel { foreach ($this->channelConfigurations as $configuration) { if ($configuration->getChannel()->getCode() === $channelCode) return $configuration; } return null; }
    private function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
