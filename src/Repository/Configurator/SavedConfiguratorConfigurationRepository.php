<?php

declare(strict_types=1);

namespace App\Repository\Configurator;

use App\Entity\Configurator\SavedConfiguratorConfiguration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SavedConfiguratorConfiguration> */
final class SavedConfiguratorConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavedConfiguratorConfiguration::class);
    }
}
