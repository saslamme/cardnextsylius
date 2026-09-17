<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use App\Entity\Taxonomy\Taxon;
use App\Repository\Seo\SeoLandingPageRepository;
use App\Seo\SeoLandingPageFilterRedirectResolver;
use App\Service\ProductAttributeProfileService;
use App\Service\ProductFacetDefinitionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;

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
}
