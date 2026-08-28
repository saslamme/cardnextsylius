<?php

declare(strict_types=1);

namespace App\Tests\Repository\Content;

use App\Entity\Channel\Channel;
use App\Entity\Content\LegalPage;
use App\Repository\Content\LegalPageRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use PHPUnit\Framework\TestCase;

final class LegalPageRepositoryTest extends TestCase
{
    private EntityManager $entityManager;

    private LegalPageRepository $repository;

    private Channel $germany;

    private Channel $austria;

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 3) . '/src/Entity/Content'], true);
        $attributeDriver = new AttributeDriver([\dirname(__DIR__, 3) . '/src/Entity/Content']);
        $configuration->setMetadataDriverImpl(new class($attributeDriver) implements MappingDriver {
            public function __construct(private readonly AttributeDriver $attributeDriver)
            {
            }

            public function loadMetadataForClass(string $className, ClassMetadataInterface $metadata): void
            {
                if (!$metadata instanceof ClassMetadata) {
                    throw new \InvalidArgumentException('ORM class metadata is required.');
                }

                if ($className === Channel::class) {
                    $metadata->setPrimaryTable(['name' => 'sylius_channel']);
                    $metadata->mapField(['fieldName' => 'id', 'type' => 'integer', 'id' => true]);

                    return;
                }

                $this->attributeDriver->loadMetadataForClass($className, $metadata);
            }

            public function getAllClassNames(): array
            {
                return [LegalPage::class, Channel::class];
            }

            public function isTransient(string $className): bool
            {
                return !\in_array($className, $this->getAllClassNames(), true);
            }
        });

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $this->entityManager = new EntityManager($connection, $configuration);
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(LegalPage::class),
            $this->entityManager->getClassMetadata(Channel::class),
        ]);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new LegalPageRepository($registry);
        $this->germany = $this->channel(1, 'CARDNEXT_DE');
        $this->austria = $this->channel(2, 'CARDNEXT_AT');
    }

    public function testSameTypeAndChannelInDifferentLocalesIsAllowed(): void
    {
        $this->persistPage('imprint', 'de_DE', $this->germany);

        self::assertSame([], $this->repository->findConflicts($this->page('imprint', 'en_GB', $this->germany)));
    }

    public function testSameTypeAndLocaleInDifferentChannelsIsAllowed(): void
    {
        $this->persistPage('imprint', 'de_DE', $this->germany);

        self::assertSame([], $this->repository->findConflicts($this->page('imprint', 'de_DE', $this->austria)));
    }

    public function testDifferentTypeInSameLocaleAndChannelIsAllowed(): void
    {
        $this->persistPage('imprint', 'de_DE', $this->germany);

        self::assertSame([], $this->repository->findConflicts($this->page('privacy', 'de_DE', $this->germany)));
    }

    public function testOverlappingChannelConflictsWhenTypeAndLocaleMatch(): void
    {
        $existing = $this->persistPage('imprint', 'de_DE', $this->germany, $this->austria);

        self::assertSame([$existing], $this->repository->findConflicts($this->page('imprint', 'de_DE', $this->austria)));
    }

    public function testFinderMatchesTypeLocaleAndActiveChannel(): void
    {
        $page = $this->persistPage('imprint', 'de_DE', $this->germany);

        self::assertSame($page, $this->repository->findOneByTypeAndChannel('imprint', $this->germany, 'de_DE'));
        self::assertNull($this->repository->findOneByTypeAndChannel('imprint', $this->austria, 'de_DE'));
    }

    public function testExistingPageDoesNotConflictWithItself(): void
    {
        $page = $this->persistPage('imprint', 'de_DE', $this->germany);

        self::assertSame([], $this->repository->findConflicts($page));
    }

    private function channel(int $id, string $code): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        (new \ReflectionProperty($channel, 'id'))->setValue($channel, $id);
        $this->entityManager->persist($channel);

        return $channel;
    }

    private function page(string $code, string $localeCode, Channel ...$channels): LegalPage
    {
        $page = new LegalPage();
        $page->setCode($code);
        $page->setLocaleCode($localeCode);
        $page->setTitle('Legal page');
        foreach ($channels as $channel) {
            $page->addChannel($channel);
        }

        return $page;
    }

    private function persistPage(string $code, string $localeCode, Channel ...$channels): LegalPage
    {
        $page = $this->page($code, $localeCode, ...$channels);
        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $page;
    }
}
