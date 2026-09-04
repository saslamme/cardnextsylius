<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Cms\CmsManufacturerSliderResolver;
use App\Entity\Product\Manufacturer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class CmsManufacturerSliderResolverTest extends TestCase
{
    public function testItBulkResolvesEnabledManufacturersInStoredOrder(): void
    {
        $a = new Manufacturer();
        $a->setCode('A');
        $b = new Manufacturer();
        $b->setCode('B');
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('getResult')->willReturn([$b, $a]);
        $builder = $this->createMock(QueryBuilder::class);
        $builder->expects(self::exactly(2))->method('andWhere')->willReturnSelf();
        $parameters = [];
        $builder->expects(self::exactly(2))->method('setParameter')->willReturnCallback(static function (string $name, mixed $value) use (&$parameters, $builder): QueryBuilder {
            $parameters[$name] = $value;

            return $builder;
        });
        $builder->expects(self::once())->method('getQuery')->willReturn($query);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('createQueryBuilder')->with('manufacturer')->willReturn($builder);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('getRepository')->with(Manufacturer::class)->willReturn($repository);

        self::assertSame([$a, $b], (new CmsManufacturerSliderResolver($manager))->resolve([' A ', 'missing', 'B', 'A'], 2));
        self::assertSame(['A', 'missing', 'B'], $parameters['codes']);
        self::assertTrue($parameters['enabled']);
    }

    public function testInvalidCodesDoNotCauseAQuery(): void
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('getRepository');
        self::assertSame([], (new CmsManufacturerSliderResolver($manager))->resolve(['', null, 1]));
    }
}
