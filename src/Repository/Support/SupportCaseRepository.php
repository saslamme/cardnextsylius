<?php

declare(strict_types=1);

namespace App\Repository\Support;

use App\Entity\Customer\Customer;
use App\Entity\Support\SupportCase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SupportCase> */
final class SupportCaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportCase::class);
    }

    /** @return list<SupportCase> */
    public function findForCustomer(Customer $customer): array
    {
        return $this->findBy(['customer' => $customer], ['createdAt' => 'DESC']);
    }

    /** @return list<SupportCase> */
    public function findForCustomerAndChannel(Customer $customer, string $channelCode): array
    {
        /** @var list<SupportCase> $cases */
        $cases = $this->createQueryBuilder('supportCase')
            ->addSelect('customerOrder', 'product', 'maintenanceContract')
            ->leftJoin('supportCase.order', 'customerOrder')
            ->leftJoin('supportCase.product', 'product')
            ->leftJoin('supportCase.maintenanceContract', 'maintenanceContract')
            ->andWhere('supportCase.customer = :customer')
            ->andWhere('supportCase.channelCode = :channelCode')
            ->setParameter('customer', $customer)
            ->setParameter('channelCode', $channelCode)
            ->orderBy('supportCase.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $cases;
    }

    public function findOneForCustomer(int $id, Customer $customer): ?SupportCase
    {
        return $this->findOneBy(['id' => $id, 'customer' => $customer]);
    }

    public function findOneForCustomerAndChannel(int $id, Customer $customer, string $channelCode): ?SupportCase
    {
        return $this->findOneBy(['id' => $id, 'customer' => $customer, 'channelCode' => $channelCode]);
    }
}
