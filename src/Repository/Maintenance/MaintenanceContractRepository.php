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
        return $this->findBy(['customer' => $customer], ['startsAt' => 'DESC', 'endsAt' => 'DESC']);
    }
}
