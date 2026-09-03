<?php
declare(strict_types=1);
namespace App\Repository\Cms;
use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsDownload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
final class CmsDownloadRepository extends ServiceEntityRepository { public function __construct(ManagerRegistry $r){parent::__construct($r,CmsDownload::class);} /** @return list<CmsDownload> */ public function findVisible(Channel $channel,string $locale,array $filters=[],?int $limit=null):array{$qb=$this->createQueryBuilder('d')->join('d.channels','c')->join('d.translations','t')->addSelect('t')->andWhere('c = :channel AND t.locale = :locale AND d.enabled = true AND (d.publishedAt IS NULL OR d.publishedAt <= :now)')->setParameters(['channel'=>$channel,'locale'=>$locale,'now'=>new \DateTimeImmutable()])->orderBy('d.manufacturer','ASC')->addOrderBy('d.position','ASC'); if($q=trim((string)($filters['q']??'')))$qb->andWhere('LOWER(t.title) LIKE :q OR LOWER(d.code) LIKE :q OR LOWER(d.manufacturer) LIKE :q')->setParameter('q','%'.strtolower($q).'%'); if($type=$filters['type']??null)$qb->andWhere('d.type = :type')->setParameter('type',$type); if($m=$filters['manufacturer']??null)$qb->andWhere('d.manufacturer = :manufacturer')->setParameter('manufacturer',$m); if($os=$filters['os']??null)$qb->andWhere('d.operatingSystems LIKE :os')->setParameter('os','%"'.$os.'"%'); if($limit)$qb->setMaxResults($limit); return $qb->getQuery()->getResult();}}
