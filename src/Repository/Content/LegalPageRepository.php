<?php

declare(strict_types=1);

namespace App\Repository\Content;

use App\Entity\Content\LegalPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Channel\Model\ChannelInterface;

/** @extends ServiceEntityRepository<LegalPage> */
final class LegalPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalPage::class);
    }

    public function findOneByTypeAndChannel(string $type, ChannelInterface $channel, string $localeCode): ?LegalPage
    {
        $page = $this->createQueryBuilder('page')
            ->innerJoin('page.channels', 'channel')
            ->andWhere('page.code = :type')
            ->andWhere('page.localeCode = :localeCode')
            ->andWhere('channel = :channel')
            ->setParameter('type', $type)
            ->setParameter('localeCode', $localeCode)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if ($page === null || $page instanceof LegalPage) {
            return $page;
        }

        throw new \UnexpectedValueException('The legal page query returned an unexpected result.');
    }

    /** @return list<LegalPage> */
    public function findConflicts(LegalPage $page): array
    {
        $query = $this->createQueryBuilder('page')
            ->innerJoin('page.channels', 'channel')
            ->andWhere('page.code = :type')
            ->andWhere('page.localeCode = :localeCode')
            ->andWhere('channel IN (:channels)')
            ->setParameter('type', $page->getCode())
            ->setParameter('localeCode', $page->getLocaleCode())
            ->setParameter('channels', $page->getChannels()->toArray())
        ;

        if ($page->getId() !== null) {
            $query->andWhere('page.id != :id')->setParameter('id', $page->getId());
        }

        $conflicts = $query->getQuery()->getResult();
        if (!\is_array($conflicts)) {
            throw new \UnexpectedValueException('The legal page conflict query returned an unexpected result.');
        }

        foreach ($conflicts as $conflict) {
            if (!$conflict instanceof LegalPage) {
                throw new \UnexpectedValueException('The legal page conflict query returned an unexpected result.');
            }
        }

        return array_values($conflicts);
    }
}
