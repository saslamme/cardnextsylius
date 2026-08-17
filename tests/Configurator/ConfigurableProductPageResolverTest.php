<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Channel\Channel;
use App\Entity\Product\Product;
use App\Entity\Product\ProductTranslation;
use App\Service\ConfigurableProductPageResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class ConfigurableProductPageResolverTest extends TestCase
{
    public function testResolveUsesDoctrineCompatibleIndividualParametersAndSetsTheRenderingLocale(): void
    {
        $channel = new Channel();
        $product = new Product();
        $translation = new ProductTranslation();
        $translation->setLocale('de_DE');
        $translation->setSlug('plastikkarten-bedrucken');
        $product->addTranslation($translation);

        $resolver = $this->resolverReturning($product, [
            'channel' => $channel,
            'locale' => 'de_DE',
            'path' => 'plastikkarten/plastikkarten-bedrucken',
            'kind' => 'configurable',
        ]);

        $resolved = $resolver->resolve('/plastikkarten/plastikkarten-bedrucken/', 'de_DE', $channel);

        self::assertSame($product, $resolved);
        self::assertSame('plastikkarten-bedrucken', $resolved->getSlug());
    }

    public function testResolveReturnsNullWhenTheQueryDoesNotMatch(): void
    {
        $channel = new Channel();
        $resolver = $this->resolverReturning(null, [
            'channel' => $channel,
            'locale' => 'fr_FR',
            'path' => 'unknown',
            'kind' => 'configurable',
        ]);

        self::assertNull($resolver->resolve('unknown', 'fr_FR', $channel));
    }

    public function testQueryRetainsAllStorefrontEligibilityConditions(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../src/Service/ConfigurableProductPageResolver.php');

        foreach ([
            'translation.locale = :locale',
            'translation.configuratorPath = :path',
            'product.enabled = true',
            'product.productKind = :kind',
            'channel = :channel',
            'configurator.enabled = true',
        ] as $condition) {
            self::assertStringContainsString($condition, $source);
        }
        self::assertStringNotContainsString('setParameters([', $source);
    }

    /** @param array<string, mixed> $expectedParameters */
    private function resolverReturning(?Product $result, array $expectedParameters): ConfigurableProductPageResolver
    {
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('getOneOrNullResult')->willReturn($result);

        $actualParameters = [];
        $queryBuilder = $this->createMock(QueryBuilder::class);
        foreach (['select', 'from', 'innerJoin', 'andWhere'] as $method) {
            $queryBuilder->method($method)->willReturnSelf();
        }
        $queryBuilder->expects(self::exactly(4))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$actualParameters, $queryBuilder): QueryBuilder {
                $actualParameters[$name] = $value;

                return $queryBuilder;
            });
        $queryBuilder->expects(self::once())
            ->method('getQuery')
            ->willReturnCallback(function () use (&$actualParameters, $expectedParameters, $query): Query {
                self::assertSame($expectedParameters, $actualParameters);

                return $query;
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        return new ConfigurableProductPageResolver($entityManager);
    }
}
