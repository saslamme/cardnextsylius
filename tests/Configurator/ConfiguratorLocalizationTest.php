<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Dto\Configurator\ConfiguratorConfiguration;
use App\Dto\Configurator\ConfiguratorPriceResult;
use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorField;
use App\Entity\Configurator\ConfiguratorFieldTranslation;
use App\Entity\Configurator\ConfiguratorLeadTime;
use App\Entity\Configurator\ConfiguratorLeadTimeTranslation;
use App\Entity\Configurator\ConfiguratorSection;
use App\Entity\Configurator\ConfiguratorSectionTranslation;
use App\Entity\Configurator\ConfiguratorValue;
use App\Entity\Configurator\ConfiguratorValueTranslation;
use App\Enum\Configurator\FieldType;
use App\Service\Configurator\ConfigurationHashGenerator;
use App\Service\Configurator\ConfiguratorLocalizationResolver;
use App\Service\Configurator\ConfiguredOrderItemSnapshotFactory;
use PHPUnit\Framework\TestCase;

final class ConfiguratorLocalizationTest extends TestCase
{
    public function testEveryStructuralEntityOwnsTranslations(): void
    {
        $configurator = new Configurator('cards', 'Cards');
        $section = new ConfiguratorSection('printing', 'Druck');
        $field = new ConfiguratorField('method', 'Druckverfahren', FieldType::SINGLE_CHOICE);
        $value = new ConfiguratorValue('offset', 'Offsetdruck');
        $leadTime = new ConfiguratorLeadTime($configurator, 'standard', 'Standardproduktion', 10);

        $sectionTranslation = new ConfiguratorSectionTranslation('es_ES', 'Impresión');
        $fieldTranslation = new ConfiguratorFieldTranslation('es_ES', 'Método de impresión');
        $valueTranslation = new ConfiguratorValueTranslation('es_ES', 'Impresión offset');
        $leadTranslation = new ConfiguratorLeadTimeTranslation('es_ES', 'Producción estándar');
        $section->addTranslation($sectionTranslation);
        $field->addTranslation($fieldTranslation);
        $value->addTranslation($valueTranslation);
        $leadTime->addTranslation($leadTranslation);

        self::assertSame($sectionTranslation, $section->getTranslation('es_ES'));
        self::assertSame($fieldTranslation, $field->getTranslation('es_ES'));
        self::assertSame($valueTranslation, $value->getTranslation('es_ES'));
        self::assertSame($leadTranslation, $leadTime->getTranslation('es_ES'));
        $section->removeTranslation($sectionTranslation);
        self::assertNull($section->getTranslation('es_ES'));
    }

    public function testResolverUsesExactGermanAndLegacyFallbacks(): void
    {
        $resolver = new ConfiguratorLocalizationResolver();
        $field = new ConfiguratorField('method', 'Legacy', FieldType::SINGLE_CHOICE);
        $field->addTranslation(new ConfiguratorFieldTranslation('de_DE', 'Deutsch'));
        $field->addTranslation(new ConfiguratorFieldTranslation('da_DK', 'Dansk'));
        $field->addTranslation(new ConfiguratorFieldTranslation('es_ES', 'Español'));

        self::assertSame('Dansk', $resolver->fieldName($field, 'da_DK'));
        self::assertSame('Español', $resolver->fieldName($field, 'es_ES'));
        self::assertSame('Deutsch', $resolver->fieldName($field, 'de_AT'));
        self::assertSame('Deutsch', $resolver->fieldName($field, 'sv_SE'));
        self::assertSame('Legacy', $resolver->fieldName(new ConfiguratorField('other', 'Legacy', FieldType::TEXT), 'sv_SE'));
    }

    public function testSnapshotKeepsNamesFromOrderingLocale(): void
    {
        $configurator = new Configurator('cards', 'Cards');
        $section = new ConfiguratorSection('printing', 'Druck');
        $field = new ConfiguratorField('method', 'Druckverfahren', FieldType::SINGLE_CHOICE);
        $value = new ConfiguratorValue('offset', 'Offsetdruck');
        $field->addTranslation(new ConfiguratorFieldTranslation('es_ES', 'Método de impresión'));
        $value->addTranslation(new ConfiguratorValueTranslation('es_ES', 'Impresión offset'));
        $field->addValue($value);
        $section->addField($field);
        $configurator->addSection($section);
        $configuration = new ConfiguratorConfiguration('cards', 1, 'EUR', 'CARDNEXT_ES', ['method' => 'offset']);
        $price = new ConfiguratorPriceResult(1, 'EUR', 100, 0, 100, 100, 0, 0, 100, []);

        $item = (new ConfiguredOrderItemSnapshotFactory(new ConfigurationHashGenerator()))->create($configurator, $configuration, $price, 'es_ES');
        $snapshot = $item->getSelectionsSnapshot()['method'];
        self::assertSame('Método de impresión', $snapshot['fieldName']);
        self::assertSame('Impresión offset', $snapshot['value']['name']);

        $field->getTranslation('es_ES')?->setName('Texto cambiado');
        self::assertSame('Método de impresión', $item->getSelectionsSnapshot()['method']['fieldName']);
    }

    public function testSpanishStorefrontTemplateUsesLocalizedTextWithoutChangingCodes(): void
    {
        $template = file_get_contents(__DIR__ . '/../../templates/shop/configurator/product.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString("configurator_text(field, 'name')", $template);
        self::assertStringContainsString("configurator_text(value, 'name')", $template);
        self::assertStringContainsString('value="{{ value.code }}"', $template);
    }
}
