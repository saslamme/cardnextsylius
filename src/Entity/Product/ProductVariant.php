<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Sylius\AdyenPlugin\Entity\CommodityCodeAwareInterface;
use Sylius\AdyenPlugin\Entity\CommodityCodeAwareTrait;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;
use Sylius\Component\Product\Model\ProductVariantTranslationInterface;
use Sylius\MolliePlugin\Entity\ProductVariantInterface;
use Sylius\MolliePlugin\Entity\RecurringProductVariantTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_variant')]
#[ORM\Index(columns: ['manufacturer_part_number_normalized'], name: 'IDX_CN_VARIANT_MPN_NORMALIZED')]
#[ORM\Index(columns: ['gtin_normalized'], name: 'IDX_CN_VARIANT_GTIN_NORMALIZED')]
class ProductVariant extends BaseProductVariant implements ProductVariantInterface, CommodityCodeAwareInterface
{
    use RecurringProductVariantTrait, CommodityCodeAwareTrait;

    #[ORM\Column(name: 'manufacturer_part_number', length: 128, nullable: true)]
    private ?string $manufacturerPartNumber = null;

    #[ORM\Column(name: 'manufacturer_part_number_normalized', length: 128, nullable: true)]
    private ?string $manufacturerPartNumberNormalized = null;

    #[ORM\Column(name: 'gtin', length: 64, nullable: true)]
    private ?string $gtin = null;

    #[ORM\Column(name: 'gtin_normalized', length: 64, nullable: true)]
    private ?string $gtinNormalized = null;

    #[ORM\Column(name: 'minimum_order_quantity', type: 'integer', options: ['default' => 1])]
    private int $minimumOrderQuantity = 1;

    #[ORM\Column(name: 'order_increment', type: 'integer', options: ['default' => 1])]
    private int $orderIncrement = 1;

    #[ORM\Column(name: 'pack_quantity', type: 'integer', options: ['default' => 1])]
    private int $packQuantity = 1;

    public function getManufacturerPartNumber(): ?string
    {
        return $this->manufacturerPartNumber;
    }

    public function setManufacturerPartNumber(?string $manufacturerPartNumber): void
    {
        $manufacturerPartNumber = $manufacturerPartNumber !== null ? trim($manufacturerPartNumber) : null;

        $this->manufacturerPartNumber = $manufacturerPartNumber !== '' ? $manufacturerPartNumber : null;
        $this->manufacturerPartNumberNormalized = $this->manufacturerPartNumber !== null
            ? self::normalizeIdentifier($this->manufacturerPartNumber)
            : null;
    }

    public function getManufacturerPartNumberNormalized(): ?string
    {
        return $this->manufacturerPartNumberNormalized;
    }

    public function getGtin(): ?string
    {
        return $this->gtin;
    }

    public function setGtin(?string $gtin): void
    {
        $gtin = $gtin !== null ? trim($gtin) : null;

        $this->gtin = $gtin !== '' ? $gtin : null;
        $this->gtinNormalized = $this->gtin !== null
            ? self::normalizeIdentifier($this->gtin)
            : null;
    }

    public function getGtinNormalized(): ?string
    {
        return $this->gtinNormalized;
    }

    public function getMinimumOrderQuantity(): int
    {
        return $this->minimumOrderQuantity;
    }

    public function setMinimumOrderQuantity(int $minimumOrderQuantity): void
    {
        $this->minimumOrderQuantity = max(1, $minimumOrderQuantity);
    }

    public function getOrderIncrement(): int
    {
        return $this->orderIncrement;
    }

    public function setOrderIncrement(int $orderIncrement): void
    {
        $this->orderIncrement = max(1, $orderIncrement);
    }

    public function getPackQuantity(): int
    {
        return $this->packQuantity;
    }

    public function setPackQuantity(int $packQuantity): void
    {
        $this->packQuantity = max(1, $packQuantity);
    }

    public function isValidOrderQuantity(int $quantity): bool
    {
        if ($quantity < $this->minimumOrderQuantity) {
            return false;
        }

        return ($quantity - $this->minimumOrderQuantity) % $this->orderIncrement === 0;
    }

    private static function normalizeIdentifier(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/[^a-z0-9]+/i', '', $value) ?? '';
    }

    protected function createTranslation(): ProductVariantTranslationInterface
    {
        return new ProductVariantTranslation();
    }
}
