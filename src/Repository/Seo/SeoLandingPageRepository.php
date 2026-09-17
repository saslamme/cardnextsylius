<?php

declare(strict_types=1);

namespace App\Repository\Seo;

use App\Entity\Channel\Channel;
use App\Entity\Seo\SeoLandingPage;
use App\Entity\Taxonomy\Taxon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SeoLandingPage> */
final class SeoLandingPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, SeoLandingPage::class); }
    public function findEnabled(Channel $channel, string $locale, string $path): ?SeoLandingPage
    {
        return $this->findOneBy(['channel' => $channel, 'locale' => $locale, 'path' => $path, 'enabled' => true]);
    }

    /** @return list<SeoLandingPage> */
    public function findEnabledForTaxon(Channel $channel, string $locale, Taxon $taxon): array
    {
        $result = $this->createQueryBuilder('page')
            ->andWhere('page.channel = :channel')
            ->andWhere('page.locale = :locale')
            ->andWhere('page.baseTaxon = :taxon')
            ->andWhere('page.enabled = :enabled')
            ->setParameter('channel', $channel)
            ->setParameter('locale', $locale)
            ->setParameter('taxon', $taxon)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();

        if (!is_array($result)) return [];

        return array_values(array_filter($result, static fn (mixed $page): bool => $page instanceof SeoLandingPage));
    }
    /** @return list<SeoLandingPage> */
    public function sitemapPages(Channel $channel, string $locale): array
    {
        return $this->findBy(['channel' => $channel, 'locale' => $locale, 'enabled' => true, 'robotsIndex' => true], ['path' => 'ASC']);
    }

    /** @return array{path: bool, metaTitle: bool, h1: bool} */
    public function findDuplicateFields(Channel $channel, string $locale, string $path, string $metaTitle, string $h1, ?int $excludeId): array
    {
        $result = ['path' => false, 'metaTitle' => false, 'h1' => false];
        foreach (['path' => $path, 'metaTitle' => trim($metaTitle), 'h1' => trim($h1)] as $field => $value) {
            if ($value === '') continue;
            $qb = $this->createQueryBuilder('page')->select('COUNT(page.id)')
                ->andWhere('page.channel = :channel')->andWhere('page.locale = :locale')->andWhere("page.$field = :value")
                ->setParameter('channel', $channel)->setParameter('locale', $locale)->setParameter('value', $value);
            if ($excludeId !== null) $qb->andWhere('page.id != :id')->setParameter('id', $excludeId);
            $result[$field] = (int) $qb->getQuery()->getSingleScalarResult() > 0;
        }
        return $result;
    }
}
