<?php

declare(strict_types=1);

namespace App\Repository\Configurator;

use App\Entity\Configurator\ConfiguratorValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

// @phpstan-ignore missingType.generics
final class ConfiguratorValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, ConfiguratorValue::class);
    }
}
