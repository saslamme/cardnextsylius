<?php

declare(strict_types=1);

namespace App\Entity\Product;

use App\Entity\Customer\Customer;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_customer_variant_price_rule')]
#[ORM\UniqueConstraint(
    name: 'UNIQ_CARDNEXT_CUSTOMER_VARIANT_PRICE_RULE',
    columns: ['variant_id', 'customer_id', 'channel_code', 'min_quantity'],
)]
#[ORM\Index(columns: ['variant_id', 'customer_id', 'channel_code', 'enabled', 'min_quantity'], name: 'IDX_CN_CUSTOMER_PRICE_LOOKUP')]
class CustomerVariantPriceRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(name: 'variant_id', nullable: false, onDelete: 'CASCADE')]
    private ProductVariant $variant;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\Column(name: 'channel_code', length: 64)]
    private string $channelCode = '';

    #[ORM\Column(name: 'min_quantity', type: 'integer', options: ['default' => 1])]
    private int $minQuantity = 1;

    /**
     * Price in the smallest currency unit, e.g. 7900 = 79.00 EUR.
     */
    #[ORM\Column(type: 'integer')]
    private int $price = 0;

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

    public function getVariant(): ProductVariant
    {
        return $this->variant;
    }

    public function setVariant(ProductVariant $variant): void
    {
        $this->variant = $variant;
        $this->touch();
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
        $this->touch();
    }

    public function getChannelCode(): string
    {
        return $this->channelCode;
    }

    public function setChannelCode(string $channelCode): void
    {
        $this->channelCode = trim($channelCode);
        $this->touch();
    }

    public function getMinQuantity(): int
    {
        return $this->minQuantity;
    }

    public function setMinQuantity(int $minQuantity): void
    {
        if ($minQuantity < 1) {
            throw new \InvalidArgumentException('Minimum quantity must be at least 1.');
        }

        $this->minQuantity = $minQuantity;
        $this->touch();
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): void
    {
        if ($price < 0) {
            throw new \InvalidArgumentException('Price must not be negative.');
        }

        $this->price = $price;
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

    private function touch(): void
    {
        if (isset($this->createdAt)) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }
}
