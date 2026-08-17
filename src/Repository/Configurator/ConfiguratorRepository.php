<?php

declare(strict_types=1); namespace App\Repository\Configurator; use App\Entity\Configurator\Configurator; use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository; use Doctrine\Persistence\ManagerRegistry;
class ConfiguratorRepository extends ServiceEntityRepository { public function __construct(ManagerRegistry $r){parent::__construct($r,Configurator::class);} public function findEnabledByCode(string $code):?Configurator{return $this->createQueryBuilder('c')->addSelect('s','f','v')->leftJoin('c.sections','s')->leftJoin('s.fields','f')->leftJoin('f.values','v')->andWhere('c.code=:code')->andWhere('c.enabled=true')->setParameter('code',$code)->orderBy('s.position','ASC')->addOrderBy('f.position','ASC')->addOrderBy('v.position','ASC')->getQuery()->getOneOrNullResult();} }
