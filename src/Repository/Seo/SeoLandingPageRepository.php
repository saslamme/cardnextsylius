<?php

declare(strict_types=1);

namespace App\Repository\Seo;

use App\Entity\Channel\Channel;
use App\Entity\Seo\SeoLandingPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class SeoLandingPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, SeoLandingPage::class); }
    public function findEnabled(Channel $channel, string $locale, string $path): ?SeoLandingPage
    {
        return $this->findOneBy(['channel' => $channel, 'locale' => $locale, 'path' => $path, 'enabled' => true]);
    }
    /** @return list<SeoLandingPage> */
    public function sitemapPages(Channel $channel, string $locale): array
    {
        return $this->findBy(['channel' => $channel, 'locale' => $locale, 'enabled' => true, 'robotsIndex' => true], ['path' => 'ASC']);
    }
}
