<?php

declare(strict_types=1);

namespace App\Repository\Configurator;

use App\Entity\Configurator\{Configurator,ConfiguratorPriceRule};
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Channel\Model\ChannelInterface;

class ConfiguratorPriceRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, ConfiguratorPriceRule::class);
    }

    /** @param list<int> $valueIds @return list<ConfiguratorPriceRule> */ public function findApplicable(Configurator $c, array $valueIds, ?ChannelInterface $channel, string $currency, int $quantity): array
    {
        $qb = $this->createQueryBuilder('r')->addSelect('v', 'f', 's', 'mf', 'ms', 'ch')->leftJoin('r.value', 'v')->leftJoin('v.field', 'f')->leftJoin('f.section', 's')->leftJoin('r.multiplierField', 'mf')->leftJoin('mf.section', 'ms')->leftJoin('r.channel', 'ch')->where('r.configurator=:c')->andWhere('r.enabled=true')->andWhere('r.currencyCode=:currency')->andWhere('r.minimumQuantity<=:quantity')->andWhere('(r.maximumQuantity IS NULL OR r.maximumQuantity>=:quantity)')->andWhere('r.value IS NULL OR r.value IN (:values)')->andWhere('r.channel IS NULL OR r.channel=:channel')->setParameters(['c' => $c,'currency' => strtoupper($currency),'quantity' => $quantity,'values' => $valueIds ?: [0],'channel' => $channel])->orderBy('r.priority', 'DESC')->addOrderBy('r.id', 'ASC');
        return $qb->getQuery()->getResult();
    }
}
