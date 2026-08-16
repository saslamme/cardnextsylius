<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProductAttributeProfileService;
use App\Service\ProductFacetDefinitionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ProductFacetDefinitionServiceTest extends TestCase
{
    public function testFilterableSelectUsesTechnicalChoiceKeysAndLocalizedLabels(): void
    {
        $profiles = new ProductAttributeProfileService($this->createMock(EntityManagerInterface::class));
        $definitions = $profiles->getFilterableDefinitionsForProfile('card_printers', 'de_DE');

        self::assertArrayHasKey('CN_PRINTER_TECHNOLOGY', $definitions);
        self::assertSame('Direct-to-Card', $definitions['CN_PRINTER_TECHNOLOGY']['choices']['direct_to_card']);
    }

    public function testNonFilterableAndUnknownAttributesAreNotExposed(): void
    {
        $profiles = new ProductAttributeProfileService($this->createMock(EntityManagerInterface::class));
        $definitions = $profiles->getFilterableDefinitionsForProfile('card_printers', 'de_DE');

        self::assertArrayNotHasKey('CN_PRINT_SPEED', $definitions);
        self::assertSame([], $profiles->getFilterableDefinitionsForProfile('unknown', 'de_DE'));
    }

    public function testFacadeProvidesStableGridValuesAndBooleanChoices(): void
    {
        $profiles = new ProductAttributeProfileService($this->createMock(EntityManagerInterface::class));
        $facets = new ProductFacetDefinitionService($profiles);

        $scannerFacets = $facets->forProfile('barcode_scanners', 'de_DE');
        $byAttribute = array_column($scannerFacets, null, 'attribute');

        self::assertSame('direct_to_card', $facets->forProfile('card_printers')[0]['choices']['Direct-to-Card']);
        self::assertSame(['Ja' => '1', 'Nein' => '0'], $byAttribute['CN_WIRELESS']['choices']);
    }
}
