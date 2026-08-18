<?php

declare(strict_types=1);

namespace App\Repository\Configurator;

use App\Entity\Channel\Channel;
use App\Entity\Configurator\Configurator;
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

    /** @return array{0: Configurator, 1: \App\Entity\Configurator\ConfiguratorTranslation}|null */
    public function findPublicByPath(string $path, string $locale, Channel $channel): ?array
    {
        $row = $this->createQueryBuilder('c')
            ->addSelect('translation')
            ->innerJoin('c.translations', 'translation')
            ->innerJoin('c.channels', 'channel', 'WITH', 'channel = :channel')
            ->andWhere('c.enabled = true')
            ->andWhere('translation.locale = :locale')
            ->andWhere('translation.path = :path')
            ->setParameter('channel', $channel)
            ->setParameter('locale', $locale)
            ->setParameter('path', trim($path, '/'))
            ->getQuery()
            ->getOneOrNullResult();
        if (!$row instanceof Configurator) {
            return null;
        }

        return [$row, $row->getTranslation($locale)];
    }

    /** @return list<Configurator> */
    public function findPublicByTaxon(\App\Entity\Taxonomy\Taxon $taxon, string $locale, Channel $channel): array
    {
        return $this->createQueryBuilder('c')->addSelect('translation', 'images')->innerJoin('c.translations', 'translation', 'WITH', 'translation.locale = :locale')->innerJoin('c.channels', 'channel', 'WITH', 'channel = :channel')->innerJoin('c.taxonAssignments', 'assignment', 'WITH', 'assignment.taxon = :taxon')->leftJoin('c.images', 'images', 'WITH', 'images.enabled = true')->where('c.enabled = true')->setParameter('locale', $locale)->setParameter('channel', $channel)->setParameter('taxon', $taxon)->orderBy('assignment.position', 'ASC')->getQuery()->getResult();
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
