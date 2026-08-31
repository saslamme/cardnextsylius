<?php

declare(strict_types=1);

namespace App\Entity\Quote;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Enum\Quote\QuoteItemType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_quote_item')]
#[ORM\Index(columns: ['quote_id', 'position'], name: 'IDX_CN_OFFER_ITEM_POSITION')]
class QuoteItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items'), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Quote $quote;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: Product::class), ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class), ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProductVariant $variant = null;

    #[ORM\Column(name: 'product_code', length: 64, nullable: true)]
    private ?string $productCode = null;

    #[ORM\Column(name: 'variant_code', length: 64, nullable: true)]
    private ?string $variantCode = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(name: 'original_unit_price', nullable: true)]
    private ?int $originalUnitPrice = null;

    #[ORM\Column(name: 'unit_price')]
    private int $unitPrice = 0;

    #[ORM\Column(name: 'discount_percent', nullable: true)]
    private ?int $discountPercent = null;

    #[ORM\Column(name: 'discount_amount', nullable: true)]
    private ?int $discountAmount = null;

    #[ORM\Column(name: 'line_subtotal')]
    private int $lineSubtotal = 0;

    #[ORM\Column(name: 'line_discount')]
    private int $lineDiscount = 0;

    #[ORM\Column(name: 'line_total')]
    private int $lineTotal = 0;

    #[ORM\Column(name: 'tax_rate', nullable: true)]
    private ?int $taxRate = null;

    #[ORM\Column(name: 'item_type', length: 16, enumType: QuoteItemType::class)]
    private QuoteItemType $itemType = QuoteItemType::Custom;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuote(): Quote
    {
        return $this->quote;
    }

    public function setQuote(Quote $value): void
    {
        $this->quote = $value;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $value): void
    {
        $this->position = $value;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $value): void
    {
        $this->product = $value;
    }

    public function getVariant(): ?ProductVariant
    {
        return $this->variant;
    }

    public function setVariant(?ProductVariant $value): void
    {
        $this->variant = $value;
    }

    public function getProductCode(): ?string
    {
        return $this->productCode;
    }

    public function setProductCode(?string $value): void
    {
        $this->productCode = $value;
    }

    public function getVariantCode(): ?string
    {
        return $this->variantCode;
    }

    public function setVariantCode(?string $value): void
    {
        $this->variantCode = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $value): void
    {
        $this->name = $value;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $value): void
    {
        $this->description = $value;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $value): void
    {
        if ($value < 1) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        } $this->quantity = $value;
    }

    public function getOriginalUnitPrice(): ?int
    {
        return $this->originalUnitPrice;
    }

    public function setOriginalUnitPrice(?int $value): void
    {
        $this->originalUnitPrice = $value;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(int $value): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Unit price cannot be negative.');
        } $this->unitPrice = $value;
    }

    public function getDiscountPercent(): ?int
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?int $value): void
    {
        $this->discountPercent = $value;
    }

    public function getDiscountAmount(): ?int
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?int $value): void
    {
        $this->discountAmount = $value;
    }

    public function getLineSubtotal(): int
    {
        return $this->lineSubtotal;
    }

    public function setLineSubtotal(int $value): void
    {
        $this->lineSubtotal = $value;
    }

    public function getLineDiscount(): int
    {
        return $this->lineDiscount;
    }

    public function setLineDiscount(int $value): void
    {
        $this->lineDiscount = $value;
    }

    public function getLineTotal(): int
    {
        return $this->lineTotal;
    }

    public function setLineTotal(int $value): void
    {
        $this->lineTotal = $value;
    }

    public function getTaxRate(): ?int
    {
        return $this->taxRate;
    }

    public function setTaxRate(?int $value): void
    {
        $this->taxRate = $value;
    }

    public function getItemType(): QuoteItemType
    {
        return $this->itemType;
    }

    public function setItemType(QuoteItemType $value): void
    {
        $this->itemType = $value;
    }
}
