<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Product\Product;
use App\Service\Configurator\ConfiguratorProvisioner;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
final class ConfigurableProductLifecycleListener
{
    public function __construct(private readonly ConfiguratorProvisioner $provisioner)
    {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        if ($entity instanceof Product) {
            $this->provisioner->ensureConfigurator($entity);
        }
    }
}
