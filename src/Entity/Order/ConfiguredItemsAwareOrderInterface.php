<?php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Order\Model\OrderInterface;

/**
 * The provider-neutral contract between configured items and Sylius order processing.
 */
interface ConfiguredItemsAwareOrderInterface extends OrderInterface
{
    /** @return Collection<int, ConfiguredOrderItem> */
    public function getConfiguredItems(): Collection;
}
