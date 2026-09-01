<?php

declare(strict_types=1);

namespace App\Repository\Content;

use App\Entity\Content\ChannelHomepageContent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Channel\Model\ChannelInterface;

/** @extends ServiceEntityRepository<ChannelHomepageContent> */
class ChannelHomepageContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelHomepageContent::class);
    }

    public function findOneForChannelAndLocale(ChannelInterface $channel, string $localeCode): ?ChannelHomepageContent
    {
        return $this->findOneBy(['channel' => $channel, 'localeCode' => $localeCode]);
    }
}
