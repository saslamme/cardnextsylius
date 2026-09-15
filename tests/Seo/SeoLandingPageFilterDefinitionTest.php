<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use App\Entity\Taxonomy\Taxon;
use App\Seo\SeoLandingPageFilterDefinition;
use App\Service\ProductAttributeProfileService;
use App\Service\ProductFacetDefinitionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SeoLandingPageFilterDefinitionTest extends TestCase
{
    private SeoLandingPageFilterDefinition $builder;

    protected function setUp(): void
    {
        $profiles = new ProductAttributeProfileService($this->createMock(EntityManagerInterface::class));
        $this->builder = new SeoLandingPageFilterDefinition(new ProductFacetDefinitionService($profiles));
    }

    public function testEmptySelectionRemainsEmpty(): void
    {
        self::assertSame([], $this->builder->normalize([], $this->taxon(), 'de_DE'));
    }

    public function testManufacturersAndMultipleAttributesUseExistingStorefrontFormat(): void
    {
        $submitted = [
            'manufacturers' => ['ZEBRA', 'MATICA', 'ZEBRA'],
            'attributes' => [
                'CN_PRINTER_TECHNOLOGY' => ['retransfer'],
                'CN_PRINT_SIDES' => ['single', 'duplex'],
            ],
        ];

        self::assertSame([
            'manufacturers' => ['ZEBRA', 'MATICA'],
            'attributes' => [
                'CN_PRINTER_TECHNOLOGY' => ['retransfer'],
                'CN_PRINT_SIDES' => ['single', 'duplex'],
            ],
        ], $this->builder->normalize($submitted, $this->taxon(), 'de_DE'));
    }

    public function testTaxonChangeDropsIncompatibleAndInvalidValues(): void
    {
        $rfid = new Taxon();
        $rfid->setCode('rfid_readers');

        self::assertSame([], $this->builder->normalize([
            'attributes' => ['CN_PRINTER_TECHNOLOGY' => ['retransfer'], 'INVALID' => ['value']],
        ], $rfid, 'de_DE'));
    }

    /** @dataProvider malformedDefinitions */
    public function testMalformedValuesCannotProduceInvalidDefinition(array $value): void
    {
        self::assertSame([], $this->builder->normalize($value, $this->taxon(), 'de_DE'));
    }

    public static function malformedDefinitions(): iterable
    {
        yield 'scalar sections' => [['manufacturers' => 'ZEBRA', 'attributes' => 'invalid']];
        yield 'unknown values' => [['attributes' => ['CN_PRINT_SIDES' => ['not-a-choice']]]];
    }

    private function taxon(): Taxon
    {
        $taxon = new Taxon();
        $taxon->setCode('card_printers');

        return $taxon;
    }
}
