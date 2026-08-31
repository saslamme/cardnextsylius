<?php

declare(strict_types=1);

namespace App\Repository\Product;

use App\Entity\Product\DeviceModel;
use App\Entity\Product\DeviceModelAlias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DeviceModel> */
final class DeviceModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceModel::class);
    }

    public function findOneByIdentifier(string $identifier): ?DeviceModel
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        // @phpstan-ignore return.type
        return $this->createQueryBuilder('device')
            ->leftJoin('device.aliases', 'alias')
            ->addSelect('alias', 'manufacturer')
            ->join('device.manufacturer', 'manufacturer')
            ->andWhere('UPPER(device.code) = :code OR LOWER(device.name) = :name OR alias.normalizedAlias = :alias')
            ->setParameter('code', mb_strtoupper($identifier))
            ->setParameter('name', mb_strtolower($identifier))
            ->setParameter('alias', DeviceModelAlias::normalize($identifier))
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
