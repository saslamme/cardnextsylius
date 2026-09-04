<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Cms\CmsProductSliderResolver;
use App\Entity\Channel\Channel;
use App\Entity\Product\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final class CmsProductSliderResolverTest extends TestCase
{
    public function testItBulkResolvesPublicProductsInStoredOrderWithoutDuplicatesAndAppliesLimit(): void
    {
        $channel = new Channel();
        $channel->setCode('CARDNEXT_DE');
        $a = new Product();
        $a->setCode('A');
        $b = new Product();
        $b->setCode('B');

        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('getResult')->willReturn([$b, $a]);
        $builder = $this->createMock(QueryBuilder::class);
        $builder->expects(self::once())->method('innerJoin')->with('product.channels', 'channel')->willReturnSelf();
        $builder->expects(self::exactly(4))->method('andWhere')->willReturnSelf();
        $parameters = [];
        $builder->expects(self::exactly(4))->method('setParameter')->willReturnCallback(
            static function (string $name, mixed $value) use (&$parameters, $builder): QueryBuilder {
                $parameters[$name] = $value;

                return $builder;
            },
        );
        $builder->expects(self::once())->method('getQuery')->willReturn($query);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('createQueryBuilder')->with('product')->willReturn($builder);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getRepository')->with(Product::class)->willReturn($repository);
        $channels = $this->createMock(ChannelContextInterface::class);
        $channels->expects(self::once())->method('getChannel')->willReturn($channel);

        $products = (new CmsProductSliderResolver($entityManager, $channels))->resolve(['A', 'missing', 'B', 'A'], 2);

        self::assertSame([$a, $b], $products);
        self::assertSame(['A', 'missing', 'B'], $parameters['codes']);
        self::assertTrue($parameters['enabled']);
        self::assertFalse($parameters['addonOnly']);
        self::assertSame($channel, $parameters['channel']);
    }

    public function testItDoesNotQueryForEmptyOrInvalidCodes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $channels = $this->createMock(ChannelContextInterface::class);
        $channels->expects(self::never())->method('getChannel');

        self::assertSame([], (new CmsProductSliderResolver($entityManager, $channels))->resolve(['', null, 12]));
    }
}
