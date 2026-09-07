<?php

declare(strict_types=1);

namespace App\Repository\Maintenance;

use App\Entity\Customer\Customer;
use App\Entity\Maintenance\MaintenanceContract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MaintenanceContract> */
final class MaintenanceContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceContract::class);
    }

    /** @return list<MaintenanceContract> */
    public function findForCustomer(Customer $customer): array
    {
        /** @var list<MaintenanceContract> $contracts */
        $contracts = $this->createQueryBuilder('contract')
            ->addSelect('devices')
            ->leftJoin('contract.devices', 'devices')
            ->andWhere('contract.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('contract.startsAt', 'DESC')
            ->addOrderBy('contract.endsAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $contracts;
    }
}
