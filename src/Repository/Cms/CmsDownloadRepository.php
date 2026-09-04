<?php

declare(strict_types=1);

namespace App\Repository\Cms;

use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsDownload;
use App\Entity\Product\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final class CmsDownloadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CmsDownload::class);
    }

    /** @return list<CmsDownload> */
    public function findVisible(Channel $channel, string $locale, array $filters = [], ?int $limit = null): array
    {
        $queryBuilder = $this->visibleQueryBuilder($channel, $locale)
            ->addSelect('t')
            ->orderBy('d.manufacturer', 'ASC')
            ->addOrderBy('d.position', 'ASC');

        if ('' !== $search = trim((string) ($filters['q'] ?? ''))) {
            $queryBuilder->andWhere('LOWER(t.title) LIKE :q OR LOWER(d.code) LIKE :q OR LOWER(d.manufacturer) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($search).'%');
        }

        $types = (array) ($filters['types'] ?? []);
        if ([] !== $types) {
            $queryBuilder->andWhere('d.type IN (:types)')->setParameter('types', $types);
        }
        if ('' !== $type = (string) ($filters['type'] ?? '')) {
            $queryBuilder->andWhere('d.type = :type')->setParameter('type', $type);
        }
        if ('' !== $manufacturer = (string) ($filters['manufacturer'] ?? '')) {
            $queryBuilder->andWhere('d.manufacturer = :manufacturer')->setParameter('manufacturer', $manufacturer);
        }
        if ('' !== $operatingSystem = (string) ($filters['os'] ?? '')) {
            $queryBuilder->andWhere('d.operatingSystems LIKE :os')->setParameter('os', '%"'.$operatingSystem.'"%');
        }
        if (null !== $limit && $limit > 0) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return list<CmsDownload> */
    public function findVisibleForProduct(Product $product, Channel $channel, string $locale): array
    {
        return $this->visibleQueryBuilder($channel, $locale)
            ->addSelect('t')
            ->innerJoin('d.products', 'p')
            ->andWhere('p = :product')
            ->setParameter('product', $product)
            ->distinct()
            ->orderBy('d.position', 'ASC')
            ->addOrderBy('d.type', 'ASC')
            ->addOrderBy('t.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @param list<string> $types @return list<string> */
    public function findVisibleManufacturers(Channel $channel, string $locale, array $types = [], string $manufacturer = ''): array
    {
        $queryBuilder = $this->visibleQueryBuilder($channel, $locale)
            ->select('DISTINCT d.manufacturer AS manufacturer')
            ->orderBy('d.manufacturer', 'ASC');

        if ([] !== $types) {
            $queryBuilder->andWhere('d.type IN (:types)')->setParameter('types', $types);
        }
        if ('' !== $manufacturer) {
            $queryBuilder->andWhere('d.manufacturer = :manufacturer')->setParameter('manufacturer', $manufacturer);
        }

        return array_column($queryBuilder->getQuery()->getArrayResult(), 'manufacturer');
    }

    private function visibleQueryBuilder(Channel $channel, string $locale): QueryBuilder
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.channels', 'c')
            ->innerJoin('d.translations', 't')
            ->andWhere('c = :channel')
            ->andWhere('t.locale = :locale')
            ->andWhere('TRIM(t.title) <> :emptyTitle')
            ->andWhere('d.enabled = true')
            ->andWhere('d.publishedAt IS NULL OR d.publishedAt <= :now')
            ->setParameter('channel', $channel)
            ->setParameter('locale', $locale)
            ->setParameter('emptyTitle', '')
            ->setParameter('now', new \DateTimeImmutable());
    }
}
