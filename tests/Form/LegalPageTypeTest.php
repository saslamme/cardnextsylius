<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Channel\Channel;
use App\Entity\Content\LegalPage;
use App\Entity\Locale\Locale;
use App\Form\Type\LegalPageType;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

final class LegalPageTypeTest extends TestCase
{
    private FormFactoryInterface $formFactory;

    private EntityManager $entityManager;

    private Channel $channel;

    protected function setUp(): void
    {
        $germany = $this->locale('de_DE');
        $austria = $this->locale('de_AT');
        $this->channel = new Channel();
        $this->channel->setCode('CARDNEXT_DE');
        $this->channel->setName('Cardnext Deutschland');

        $localeRepository = $this->repository($this->localeResources($germany, $austria));
        $channelRepository = $this->repository($this->channelResources($this->channel));
        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addType(new ChannelChoiceType($channelRepository))
            ->addType(new LegalPageType($localeRepository))
            ->getFormFactory()
        ;

        $configuration = ORMSetup::createAttributeMetadataConfiguration([
            \dirname(__DIR__, 2) . '/src/Entity/Content',
        ], true);
        $attributeDriver = new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity/Content']);
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

        $this->entityManager = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration),
            $configuration,
        );
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(LegalPage::class),
            $this->entityManager->getClassMetadata(Channel::class),
        ]);
        (new \ReflectionProperty($this->channel, 'id'))->setValue($this->channel, 1);
        $this->entityManager->persist($this->channel);
    }

    public function testNewPageMapsConfiguredLocaleCodeAndChannelAndPersistsCode(): void
    {
        $page = new LegalPage();
        $form = $this->formFactory->create(LegalPageType::class, $page);

        // Rendering a fresh page used to fail because its string locale was mapped to locale entities.
        $localeChoices = $form->get('localeCode')->createView()->vars['choices'];
        self::assertSame('de_DE', $localeChoices[0]->value);
        self::assertSame($this->locale('de_DE')->getName(), $localeChoices[0]->label);
        $form->submit([
            'code' => 'imprint',
            'localeCode' => 'de_DE',
            'channels' => ['CARDNEXT_DE'],
            'title' => 'Impressum',
            'content' => '<p>Legal notice</p>',
            'metaTitle' => '',
            'metaDescription' => '',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('de_DE', $page->getLocaleCode());
        self::assertNotSame('Deutsch (Deutschland)', $page->getLocaleCode());
        self::assertSame([$this->channel], $page->getChannels()->toArray());

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        self::assertSame(
            'de_DE',
            $this->entityManager->getConnection()->fetchOne(
                'SELECT locale_code FROM cardnext_legal_page WHERE id = ?',
                [$page->getId()],
            ),
        );
    }

    public function testAustrianLocaleMapsToAustrianCode(): void
    {
        $page = new LegalPage();
        $form = $this->formFactory->create(LegalPageType::class, $page);
        $form->submit($this->validData('de_AT'));

        self::assertTrue($form->isSynchronized());
        self::assertSame('de_AT', $page->getLocaleCode());
        self::assertNotSame('Deutsch (Österreich)', $page->getLocaleCode());
    }

    public function testExistingStringLocaleIsPreselected(): void
    {
        $page = new LegalPage();
        $page->setLocaleCode('de_DE');

        $form = $this->formFactory->create(LegalPageType::class, $page);

        self::assertSame('de_DE', $form->get('localeCode')->getViewData());
    }

    /** @return array<string, string|array<string>> */
    private function validData(string $localeCode): array
    {
        return [
            'code' => 'imprint',
            'localeCode' => $localeCode,
            'channels' => ['CARDNEXT_DE'],
            'title' => 'Impressum',
            'content' => '<p>Legal notice</p>',
            'metaTitle' => '',
            'metaDescription' => '',
        ];
    }

    private function locale(string $code): Locale
    {
        $locale = new Locale();
        $locale->setCode($code);

        return $locale;
    }

    /** @return array<LocaleInterface> */
    private function localeResources(LocaleInterface ...$locales): array
    {
        return $locales;
    }

    /** @return array<ChannelInterface> */
    private function channelResources(ChannelInterface ...$channels): array
    {
        return $channels;
    }

    /**
     * @template T of \Sylius\Resource\Model\ResourceInterface
     *
     * @param array<T> $items
     *
     * @return RepositoryInterface<T>&MockObject
     */
    private function repository(array $items): RepositoryInterface&MockObject
    {
        $repository = $this->createMock(RepositoryInterface::class);
        $repository->method('findAll')->willReturn($items);

        return $repository;
    }
}
