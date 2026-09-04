<?php

declare(strict_types=1);

namespace App\Entity\Order;

use App\Entity\Product\ProductVariant;
use App\Entity\Product\ProductBundle;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item')]
class OrderItem extends BaseOrderItem
{
    public const ADDON_TYPE_MAINTENANCE = 'maintenance';
    public const BUNDLE_ROLE_MAIN = 'MAIN';
    public const BUNDLE_ROLE_COMPONENT = 'COMPONENT';

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parentItem = null;

    #[ORM\Column(name: 'addon_type', length: 32, nullable: true)]
    private ?string $addonType = null;

    #[ORM\ManyToOne(targetEntity: ProductBundle::class)]
    #[ORM\JoinColumn(name: 'bundle_id', nullable: true, onDelete: 'SET NULL')]
    private ?ProductBundle $bundle = null;

    #[ORM\Column(name: 'bundle_group_key', length: 36, nullable: true)]
    private ?string $bundleGroupKey = null;

    #[ORM\Column(name: 'bundle_role', length: 16, nullable: true)]
    #[Assert\Choice(choices: [self::BUNDLE_ROLE_MAIN, self::BUNDLE_ROLE_COMPONENT])]
    private ?string $bundleRole = null;

    public function getParentItem(): ?self
    {
        return $this->parentItem;
    }

    public function setParentItem(?self $parentItem): void
    {
        $this->parentItem = $parentItem;
    }

    public function getAddonType(): ?string
    {
        return $this->addonType;
    }

    public function setAddonType(?string $addonType): void
    {
        $this->addonType = $addonType;
    }

    public function isMaintenanceAddon(): bool
    {
        return self::ADDON_TYPE_MAINTENANCE === $this->addonType;
    }

    public function getBundle(): ?ProductBundle { return $this->bundle; }
    public function setBundle(?ProductBundle $bundle): void { $this->bundle = $bundle; }
    public function getBundleGroupKey(): ?string { return $this->bundleGroupKey; }
    public function setBundleGroupKey(?string $key): void { $this->bundleGroupKey = $key; }
    public function getBundleRole(): ?string { return $this->bundleRole; }
    public function setBundleRole(?string $role): void
    {
        if ($role !== null && !in_array($role, [self::BUNDLE_ROLE_MAIN, self::BUNDLE_ROLE_COMPONENT], true)) throw new \InvalidArgumentException('Invalid bundle role.');
        $this->bundleRole = $role;
    }

    public function isBundleItem(): bool { return $this->bundle !== null && $this->bundleGroupKey !== null; }

    #[Assert\Callback]
    public function validateCardnextOrderQuantity(ExecutionContextInterface $context): void
    {
        $variant = $this->getVariant();
        if (!$variant instanceof ProductVariant || $variant->isValidOrderQuantity($this->getQuantity())) {
            return;
        }

        if ($this->getQuantity() < $variant->getMinimumOrderQuantity()) {
            $context->buildViolation('Die Mindestbestellmenge für diesen Artikel beträgt {{ minimum }}.')
                ->setParameter('{{ minimum }}', (string) $variant->getMinimumOrderQuantity())
                ->atPath('quantity')
                ->addViolation();

            return;
        }

        $context->buildViolation('Die Menge muss ab {{ minimum }} in Schritten von {{ increment }} bestellt werden.')
            ->setParameter('{{ minimum }}', (string) $variant->getMinimumOrderQuantity())
            ->setParameter('{{ increment }}', (string) $variant->getOrderIncrement())
            ->atPath('quantity')
            ->addViolation();
    }
}
