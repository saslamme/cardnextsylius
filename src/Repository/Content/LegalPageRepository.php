<?php

declare(strict_types=1);

namespace App\Repository\Content;

use App\Entity\Content\LegalPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Channel\Model\ChannelInterface;

final class LegalPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalPage::class);
    }

    public function findOneByTypeAndChannel(string $type, ChannelInterface $channel, string $localeCode): ?LegalPage
    {
        return $this->createQueryBuilder('page')
            ->innerJoin('page.channels', 'channel')
            ->andWhere('page.code = :type')
            ->andWhere('page.localeCode = :localeCode')
            ->andWhere('channel = :channel')
            ->setParameters(['type' => $type, 'localeCode' => $localeCode, 'channel' => $channel])
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /** @return list<LegalPage> */
    public function findConflicts(LegalPage $page): array
    {
        $query = $this->createQueryBuilder('page')
            ->innerJoin('page.channels', 'channel')
            ->andWhere('page.code = :type')
            ->andWhere('channel IN (:channels)')
            ->setParameter('type', $page->getCode())
            ->setParameter('channels', $page->getChannels()->toArray())
        ;

        if ($page->getId() !== null) {
            $query->andWhere('page.id != :id')->setParameter('id', $page->getId());
        }

        return $query->getQuery()->getResult();
    }
}
