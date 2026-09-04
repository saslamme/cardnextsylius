<?php

declare(strict_types=1);

namespace App\Entity\Product;

use App\Entity\Channel\Channel;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_product_bundle_channel')]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_BUNDLE_CHANNEL', columns: ['bundle_id', 'channel_id'])]
class ProductBundleChannel
{
    public const DISCOUNT_NONE = 'NONE';
    public const DISCOUNT_FIXED = 'FIXED';
    public const DISCOUNT_PERCENT = 'PERCENT';

    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column(type: 'integer')] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: ProductBundle::class, inversedBy: 'channelConfigurations')]
    #[ORM\JoinColumn(name: 'bundle_id', nullable: false, onDelete: 'CASCADE')]
    private ProductBundle $bundle;
    #[ORM\ManyToOne(targetEntity: Channel::class)]
    #[ORM\JoinColumn(name: 'channel_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Channel $channel;
    #[ORM\Column(options: ['default' => true])] private bool $enabled = true;
    #[ORM\Column(name: 'discount_type', length: 16, options: ['default' => self::DISCOUNT_NONE])]
    #[Assert\Choice(choices: [self::DISCOUNT_NONE, self::DISCOUNT_FIXED, self::DISCOUNT_PERCENT])]
    private string $discountType = self::DISCOUNT_NONE;
    #[ORM\Column(name: 'fixed_discount', type: 'integer', nullable: true)] #[Assert\PositiveOrZero] private ?int $fixedDiscount = null;
    /** Percentage in basis points: 500 means 5.00%. */
    #[ORM\Column(name: 'percentage_discount', type: 'integer', nullable: true)] #[Assert\Range(min: 0, max: 10000)] private ?int $percentageDiscount = null;

    public function getId(): ?int { return $this->id; }
    public function getBundle(): ProductBundle { return $this->bundle; }
    public function setBundle(ProductBundle $bundle): void { $this->bundle = $bundle; }
    public function getChannel(): Channel { return $this->channel; }
    public function hasChannel(): bool { return isset($this->channel); }
    public function setChannel(Channel $channel): void { $this->channel = $channel; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): void { $this->enabled = $enabled; }
    public function getDiscountType(): string { return $this->discountType; }
    public function setDiscountType(string $type): void { if (!in_array($type, [self::DISCOUNT_NONE, self::DISCOUNT_FIXED, self::DISCOUNT_PERCENT], true)) throw new \InvalidArgumentException('Invalid bundle discount type.'); $this->discountType = $type; }
    public function getFixedDiscount(): ?int { return $this->fixedDiscount; }
    public function setFixedDiscount(?int $discount): void { $this->fixedDiscount = $discount; }
    public function getPercentageDiscount(): ?int { return $this->percentageDiscount; }
    public function setPercentageDiscount(?int $discount): void { $this->percentageDiscount = $discount; }
    public function calculateDiscount(int $subtotal, int $bundleQuantity = 1): int { if ($subtotal <= 0) return 0; $amount = match ($this->discountType) { self::DISCOUNT_FIXED => max(0, (int) $this->fixedDiscount) * max(1, $bundleQuantity), self::DISCOUNT_PERCENT => intdiv($subtotal * max(0, (int) $this->percentageDiscount) + 5000, 10000), default => 0 }; return min($subtotal, $amount); }

    #[Assert\Callback]
    public function validateDiscount(ExecutionContextInterface $context): void
    {
        if ($this->discountType === self::DISCOUNT_FIXED && $this->fixedDiscount === null) $context->buildViolation('Für einen festen Rabatt ist ein Betrag erforderlich.')->atPath('fixedDiscount')->addViolation();
        if ($this->discountType === self::DISCOUNT_PERCENT && $this->percentageDiscount === null) $context->buildViolation('Für einen prozentualen Rabatt ist ein Wert erforderlich.')->atPath('percentageDiscount')->addViolation();
    }
}
