<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Configurator\Configurator;
use App\Repository\Configurator\ConfiguratorPriceRuleRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfiguratorPriceRuleRepositoryTest extends TestCase
{
    /** @return iterable<string, array{list<int>, int}> */
    public static function applicableRuleInputs(): iterable
    {
        yield 'empty values and quantity 100' => [[], 100];
        yield 'selected value and quantity 250' => [[42], 250];
    }

    /** @param list<int> $valueIds */
    #[DataProvider('applicableRuleInputs')]
    public function testFindApplicableExecutesWithDoctrineQueryBuilder(array $valueIds, int $quantity): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([
            \dirname(__DIR__, 2) . '/src/Entity',
        ], true);
        $attributeDriver = new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity']);
        $configuration->setMetadataDriverImpl(new class($attributeDriver) implements MappingDriver {
            public function __construct(private readonly AttributeDriver $attributeDriver)
            {
            }

            public function loadMetadataForClass(string $className, ClassMetadataInterface $metadata): void
            {
                if (\in_array($className, [\App\Entity\Channel\Channel::class, \App\Entity\Product\Product::class], true)) {
                    \assert($metadata instanceof ClassMetadata);
                    $metadata->setPrimaryTable(['name' => $className === \App\Entity\Channel\Channel::class ? 'sylius_channel' : 'sylius_product']);
                    $metadata->mapField(['fieldName' => 'id', 'type' => 'integer', 'id' => true]);

                    return;
                }

                $this->attributeDriver->loadMetadataForClass($className, $metadata);
            }

            public function getAllClassNames(): array
            {
                return $this->attributeDriver->getAllClassNames();
            }

            public function isTransient(string $className): bool
            {
                return !\in_array($className, [\App\Entity\Channel\Channel::class, \App\Entity\Product\Product::class], true) && $this->attributeDriver->isTransient($className);
            }
        });
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $entityManager = new EntityManager($connection, $configuration);

        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Configurator::class),
            $entityManager->getClassMetadata(\App\Entity\Configurator\ConfiguratorPriceRule::class),
            $entityManager->getClassMetadata(\App\Entity\Configurator\ConfiguratorValue::class),
            $entityManager->getClassMetadata(\App\Entity\Configurator\ConfiguratorField::class),
            $entityManager->getClassMetadata(\App\Entity\Configurator\ConfiguratorSection::class),
            $entityManager->getClassMetadata(\App\Entity\Channel\Channel::class),
        ]);

        $configurator = new Configurator('print', 'Print');
        $id = new \ReflectionProperty($configurator, 'id');
        $id->setValue($configurator, 1);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $repository = new ConfiguratorPriceRuleRepository($registry);

        self::assertSame([], $repository->findApplicable($configurator, $valueIds, null, 'eur', $quantity));
    }
}
