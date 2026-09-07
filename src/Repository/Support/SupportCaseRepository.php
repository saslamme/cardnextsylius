<?php
declare(strict_types=1);
namespace App\Repository\Support;
use App\Entity\Customer\Customer; use App\Entity\Support\SupportCase; use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository; use Doctrine\Persistence\ManagerRegistry;
final class SupportCaseRepository extends ServiceEntityRepository { public function __construct(ManagerRegistry $r){parent::__construct($r,SupportCase::class);} /** @return list<SupportCase> */ public function findForCustomer(Customer $c):array{return $this->findBy(['customer'=>$c],['createdAt'=>'DESC']);} public function findOneForCustomer(int $id,Customer $c):?SupportCase{return $this->findOneBy(['id'=>$id,'customer'=>$c]);} }
