<?php

declare(strict_types=1);

namespace App\Repository\Configurator;

use App\Entity\Configurator\Configurator;
use App\Entity\Product\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConfiguratorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, Configurator::class);
    }

    public function findEnabledByCode(string $code): ?Configurator
    {
        $configurator = $this->createGraphQueryBuilder()
            ->andWhere('c.code = :code')
            ->andWhere('c.enabled = true')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
        if ($configurator === null) {
            return null;
        }
        $this->initializeDependencies($configurator);

        return $configurator;
    }

    public function findEnabledByProduct(Product $product): ?Configurator
    {
        $configurator = $this->createGraphQueryBuilder()
            ->andWhere('c.product = :product')
            ->andWhere('c.enabled = true')
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult();

        if ($configurator !== null) {
            $this->initializeDependencies($configurator);
        }

        return $configurator;
    }

    private function createGraphQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->addSelect('s', 'f', 'v')
            ->leftJoin('c.sections', 's')
            ->leftJoin('s.fields', 'f')
            ->leftJoin('f.values', 'v')
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('f.position', 'ASC')
            ->addOrderBy('v.position', 'ASC');
    }

    private function initializeDependencies(Configurator $configurator): void
    {
        // Keep this separate from the section graph to avoid a cartesian product.
        $this->createQueryBuilder('c')
            ->addSelect('d', 'ds', 'dt', 'dv')
            ->leftJoin('c.dependencies', 'd')
            ->leftJoin('d.sourceField', 'ds')
            ->leftJoin('d.targetField', 'dt')
            ->leftJoin('d.targetValue', 'dv')
            ->andWhere('c = :configurator')
            ->setParameter('configurator', $configurator)
            ->orderBy('d.priority', 'ASC')
            ->addOrderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
