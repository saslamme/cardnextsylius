<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Sylius\Component\Shipping\Model\ShipmentUnit;

/**
 * Keeps Sylius' unused standalone shipment-unit mapping valid on ORM 3.7.
 *
 * Sylius Core uses OrderItemUnit as the shipment_unit resource. Its association
 * remains bidirectional; only the Shipping component's standalone mapped
 * superclass must not claim the same inverse collection.
 */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
final class ShipmentUnitMetadataListener
{
    public function __invoke(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        if ($metadata->name !== ShipmentUnit::class || !isset($metadata->associationMappings['shipment'])) {
            return;
        }

        $metadata->associationMappings['shipment']->inversedBy = null;
    }
}
