<?php

declare(strict_types=1);

namespace App\Repository\Configurator;

use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorPriceRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Channel\Model\ChannelInterface;

class ConfiguratorPriceRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, ConfiguratorPriceRule::class);
    }

    /** @param list<int> $valueIds @return list<ConfiguratorPriceRule> */
    public function findApplicable(Configurator $c, array $valueIds, ?ChannelInterface $channel, string $currency, int $quantity): array
    {
        $qb = $this->createQueryBuilder('r')->addSelect('v', 'f', 's', 'mf', 'ms', 'ch')->leftJoin('r.value', 'v')->leftJoin('v.field', 'f')->leftJoin('f.section', 's')->leftJoin('r.multiplierField', 'mf')->leftJoin('mf.section', 'ms')->leftJoin('r.channel', 'ch')->where('r.configurator=:c')->andWhere('r.enabled=true')->andWhere('r.currencyCode=:currency')->andWhere('r.minimumQuantity<=:quantity')->andWhere('(r.maximumQuantity IS NULL OR r.maximumQuantity>=:quantity)')->andWhere('r.value IS NULL OR IDENTITY(r.value) IN (:valueIds)')->andWhere('r.channel IS NULL OR r.channel=:channel')->setParameter('c', $c)->setParameter('currency', strtoupper($currency))->setParameter('quantity', $quantity)->setParameter('valueIds', $valueIds ?: [0])->setParameter('channel', $channel)->orderBy('r.priority', 'DESC')->addOrderBy('r.chargeCode', 'ASC')->addOrderBy('r.id', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
