<?php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Order as BaseOrder;
use Sylius\MolliePlugin\Entity\AbandonedEmailOrderTrait;
use Sylius\MolliePlugin\Entity\MolliePaymentIdOrderTrait;
use Sylius\MolliePlugin\Entity\OrderInterface;
use Sylius\MolliePlugin\Entity\QRCodeOrderTrait;
use Sylius\MolliePlugin\Entity\RecurringOrderTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order')]
class Order extends BaseOrder implements OrderInterface
{
    use MolliePaymentIdOrderTrait;
    use QRCodeOrderTrait;
    use RecurringOrderTrait;
    use AbandonedEmailOrderTrait;

    /** @var Collection<int, ConfiguredOrderItem> */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: ConfiguredOrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $configuredItems;

    public function __construct()
    {
        parent::__construct();
        $this->configuredItems = new ArrayCollection();
    }

    /** @return Collection<int, ConfiguredOrderItem> */
    public function getConfiguredItems(): Collection
    {
        return $this->configuredItems;
    }

    public function addConfiguredItem(ConfiguredOrderItem $item): void
    {
        if (!$this->configuredItems->contains($item)) {
            $this->configuredItems->add($item);
            $item->setOrder($this);
        }
    }

    public function removeConfiguredItem(ConfiguredOrderItem $item): void
    {
        $this->configuredItems->removeElement($item);
    }

    public function hasConfiguredItems(): bool
    {
        return !$this->configuredItems->isEmpty();
    }
}
