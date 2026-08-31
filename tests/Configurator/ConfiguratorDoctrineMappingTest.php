<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorDependency;
use App\Entity\Configurator\ConfiguratorField;
use App\Entity\Configurator\ConfiguratorLeadTime;
use App\Entity\Configurator\ConfiguratorPriceRule;
use App\Entity\Configurator\ConfiguratorSection;
use App\Entity\Configurator\ConfiguratorValue;
use App\Entity\Product\Product;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\TestCase;

final class ConfiguratorDoctrineMappingTest extends TestCase
{
    public function testPhysicalColumnNamesMatchTheDeployedConfiguratorSchema(): void
    {
        $driver = new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity/Configurator']);

        $columns = [
            Configurator::class => [
                'id' => 'id', 'code' => 'code', 'name' => 'name', 'enabled' => 'enabled',
                'shippingRequired' => 'shipping_required',
                'createdAt' => 'created_at', 'updatedAt' => 'updated_at',
            ],
            ConfiguratorSection::class => [
                'id' => 'id', 'code' => 'code', 'name' => 'name', 'description' => 'description',
                'position' => 'position', 'enabled' => 'enabled',
            ],
            ConfiguratorField::class => [
                'id' => 'id', 'code' => 'code', 'name' => 'name', 'description' => 'description',
                'helpText' => 'help_text', 'type' => 'type', 'required' => 'required', 'position' => 'position',
                'enabled' => 'enabled', 'minimumValue' => 'minimum_value', 'maximumValue' => 'maximum_value',
                'step' => 'step',
            ],
            ConfiguratorValue::class => [
                'id' => 'id', 'code' => 'code', 'name' => 'name', 'description' => 'description',
                'position' => 'position', 'enabled' => 'enabled', 'preselected' => 'preselected', 'colorHex' => 'color_hex',
                'imagePath' => 'image_path', 'icon' => 'icon',
            ],
            ConfiguratorPriceRule::class => [
                'id' => 'id', 'currencyCode' => 'currency_code', 'chargeCode' => 'charge_code',
                'label' => 'label', 'minimumQuantity' => 'minimum_quantity', 'maximumQuantity' => 'maximum_quantity',
                'priceType' => 'price_type', 'amount' => 'amount', 'multiplierType' => 'multiplier_type',
                'percentageBase' => 'percentage_base', 'priority' => 'priority', 'enabled' => 'enabled',
                'createdAt' => 'created_at', 'updatedAt' => 'updated_at',
            ],
            ConfiguratorDependency::class => [
                'id' => 'id', 'operator' => 'operator', 'expectedValues' => 'expected_values',
                'effect' => 'effect', 'priority' => 'priority', 'enabled' => 'enabled',
            ],
            ConfiguratorLeadTime::class => [
                'id' => 'id', 'code' => 'code', 'name' => 'name', 'description' => 'description',
                'workingDays' => 'working_days', 'position' => 'position', 'enabled' => 'enabled', 'preselected' => 'preselected',
            ],
        ];

        $joinColumns = [
            Configurator::class => ['taxCategory' => 'tax_category_id'],
            ConfiguratorSection::class => ['configurator' => 'configurator_id'],
            ConfiguratorField::class => ['section' => 'section_id', 'configurator' => 'configurator_id'],
            ConfiguratorValue::class => ['field' => 'field_id'],
            ConfiguratorPriceRule::class => [
                'configurator' => 'configurator_id', 'value' => 'value_id', 'channel' => 'channel_id',
                'multiplierField' => 'multiplier_field_id',
            ],
            ConfiguratorDependency::class => [
                'configurator' => 'configurator_id', 'sourceField' => 'source_field_id',
                'targetField' => 'target_field_id', 'targetValue' => 'target_value_id',
            ],
            ConfiguratorLeadTime::class => ['configurator' => 'configurator_id'],
        ];

        foreach ($columns as $class => $expected) {
            $metadata = new ClassMetadata($class);
            $driver->loadMetadataForClass($class, $metadata);
            foreach ($expected as $property => $columnName) {
                self::assertSame($columnName, $metadata->getColumnName($property), $class . '::' . $property);
            }
            foreach ($joinColumns[$class] as $property => $columnName) {
                self::assertSame($columnName, $metadata->getSingleAssociationJoinColumnName($property), $class . '::' . $property);
            }
        }

        $valueMetadata = new ClassMetadata(ConfiguratorValue::class);
        $driver->loadMetadataForClass(ConfiguratorValue::class, $valueMetadata);
        $valueOptions = $valueMetadata->getFieldMapping('preselected')->options;
        self::assertIsArray($valueOptions);
        self::assertArrayHasKey('default', $valueOptions);
        self::assertFalse($valueOptions['default']);

        $leadTimeMetadata = new ClassMetadata(ConfiguratorLeadTime::class);
        $driver->loadMetadataForClass(ConfiguratorLeadTime::class, $leadTimeMetadata);
        $leadTimeOptions = $leadTimeMetadata->getFieldMapping('preselected')->options;
        self::assertIsArray($leadTimeOptions);
        self::assertArrayHasKey('default', $leadTimeOptions);
        self::assertFalse($leadTimeOptions['default']);
    }

    public function testProductAndConfiguratorMappingsAreIndependent(): void
    {
        $configuratorMetadata = new ClassMetadata(Configurator::class);
        (new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity/Configurator']))->loadMetadataForClass(Configurator::class, $configuratorMetadata);
        $productMetadata = new ClassMetadata(Product::class);
        (new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity/Product']))->loadMetadataForClass(Product::class, $productMetadata);

        self::assertFalse($configuratorMetadata->hasAssociation('product'));
        self::assertFalse($productMetadata->hasAssociation('configurator'));
    }
}
