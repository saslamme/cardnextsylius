<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use App\Entity\Channel\Channel;
use App\Entity\Seo\SeoLandingPage;
use App\Entity\Taxonomy\Taxon;
use App\Repository\Seo\SeoLandingPageRepository;
use App\Seo\SeoLandingPageFilterRedirectResolver;
use App\Service\ProductAttributeProfileService;
use App\Service\ProductFacetDefinitionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;

final class SeoLandingPageFilterRedirectResolverTest extends TestCase
{
    private SeoLandingPageFilterRedirectResolver $resolver;
    private Taxon $taxon;

    protected function setUp(): void
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        $profiles = new ProductAttributeProfileService($manager);
        $this->resolver = new SeoLandingPageFilterRedirectResolver(
            new SeoLandingPageRepository($registry),
            $this->createMock(TaxonRepositoryInterface::class),
            $this->createMock(ChannelContextInterface::class),
            $this->createMock(LocaleContextInterface::class),
            new ProductFacetDefinitionService($profiles),
            new NullLogger(),
        );
        $this->taxon = new Taxon();
        $this->taxon->setCode('card_printers');
    }

    public function testCanonicalSignatureIgnoresOrderWhitespaceAndDuplicates(): void
    {
        self::assertSame(
            ['manufacturers' => ['MATICA', 'ZEBRA'], 'attributes' => ['A' => ['a'], 'B' => ['x', 'z']]],
            $this->resolver->canonicalize([
                'manufacturers' => [' ZEBRA ', 'MATICA', 'ZEBRA'],
                'attributes' => ['B' => ['z', 'x', 'z'], 'A' => ['', 'a']],
            ]),
        );
    }

    public function testManufacturerPayloadShapesHaveIdenticalSignatures(): void
    {
        $flat = $this->resolver->criteriaSignature(['manufacturer' => ['LEGACY_MFR_ZEBRA']], $this->taxon, 'de_DE');
        $wrapped = $this->resolver->criteriaSignature(['manufacturer' => ['value' => ['LEGACY_MFR_ZEBRA']]], $this->taxon, 'de_DE');

        self::assertSame(['manufacturers' => ['LEGACY_MFR_ZEBRA']], $flat);
        self::assertSame($flat, $wrapped);
    }

    public function testFacetNameIsMappedToAttributeCode(): void
    {
        self::assertSame(
            [
                'manufacturers' => ['LEGACY_MFR_ZEBRA'],
                'attributes' => ['CN_PRINT_SIDES' => ['duplex']],
            ],
            $this->resolver->criteriaSignature([
                'cn_print_sides' => ['value' => ['duplex']],
                'manufacturer' => ['LEGACY_MFR_ZEBRA'],
            ], $this->taxon, 'de_DE'),
        );
    }

    public function testUnknownActiveFilterRejectsEntireSignature(): void
    {
        self::assertNull($this->resolver->criteriaSignature([
            'manufacturer' => ['LEGACY_MFR_ZEBRA'],
            'something_unknown' => ['active'],
        ], $this->taxon, 'de_DE'));
    }

    public function testEmptyUnknownFilterDoesNotPreventMatch(): void
    {
        self::assertSame(
            ['manufacturers' => ['LEGACY_MFR_ZEBRA']],
            $this->resolver->criteriaSignature([
                'manufacturer' => ['LEGACY_MFR_ZEBRA'],
                'something_unknown' => ['value' => []],
            ], $this->taxon, 'de_DE'),
        );
    }

    /** @dataProvider landingPagePaths */
    public function testResolveReturnsAPathWithExactlyOneLeadingSlash(string $storedPath): void
    {
        $page = new SeoLandingPage();
        $page->setFilterDefinition(['manufacturers' => ['LEGACY_MFR_ZEBRA']]);
        (new \ReflectionProperty($page, 'path'))->setValue($page, $storedPath);

        $manager = $this->createMock(EntityManagerInterface::class);
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$page]);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $manager->method('createQueryBuilder')->willReturn($queryBuilder);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        $channel = new Channel();
        $taxons = $this->createMock(TaxonRepositoryInterface::class);
        $taxons->method('findOneBySlug')->with('kartendrucker', 'de_DE')->willReturn($this->taxon);
        $channels = $this->createMock(ChannelContextInterface::class);
        $channels->method('getChannel')->willReturn($channel);
        $locales = $this->createMock(LocaleContextInterface::class);
        $locales->method('getLocaleCode')->willReturn('de_DE');
        $profiles = new ProductAttributeProfileService($manager);
        $resolver = new SeoLandingPageFilterRedirectResolver(
            new SeoLandingPageRepository($registry),
            $taxons,
            $channels,
            $locales,
            new ProductFacetDefinitionService($profiles),
            new NullLogger(),
        );
        $request = Request::create('/kartendrucker', 'GET', [
            'limit' => '9',
            'page' => '1',
            'criteria' => ['manufacturer' => ['LEGACY_MFR_ZEBRA']],
        ]);
        $request->attributes->set('slug', 'kartendrucker');

        $result = $resolver->resolve($request);

        self::assertSame('/kartendrucker/zebra', $result);
        self::assertStringStartsNotWith('//', $result);
    }

    /** @return iterable<string, array{string}> */
    public static function landingPagePaths(): iterable
    {
        yield 'normalized path' => ['/kartendrucker/zebra'];
        yield 'path without leading slash' => ['kartendrucker/zebra'];
    }
}
