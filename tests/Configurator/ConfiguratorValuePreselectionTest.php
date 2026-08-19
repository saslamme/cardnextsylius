<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Configurator\ConfiguratorField;
use App\Entity\Configurator\ConfiguratorValue;
use App\Enum\Configurator\FieldType;
use PHPUnit\Framework\TestCase;

final class ConfiguratorValuePreselectionTest extends TestCase
{
    public function testValueIsNotPreselectedByDefault(): void
    {
        self::assertFalse((new ConfiguratorValue('digital', 'Digital print'))->isPreselected());
    }

    public function testSingleChoiceKeepsOnlyTheMostRecentlyPreselectedValue(): void
    {
        $field = new ConfiguratorField('printing', 'Printing', FieldType::SINGLE_CHOICE);
        $digital = new ConfiguratorValue('digital', 'Digital print');
        $screen = new ConfiguratorValue('screen', 'Screen print');
        $field->addValue($digital);
        $field->addValue($screen);

        $digital->setPreselected(true);
        self::assertTrue($digital->isPreselected());

        $screen->setPreselected(true);
        self::assertFalse($digital->isPreselected());
        self::assertTrue($screen->isPreselected());
    }

    public function testAttachingPreselectedSingleChoiceValueClearsItsSibling(): void
    {
        $field = new ConfiguratorField('printing', 'Printing', FieldType::SINGLE_CHOICE);
        $digital = new ConfiguratorValue('digital', 'Digital print');
        $digital->setPreselected(true);
        $field->addValue($digital);
        $screen = new ConfiguratorValue('screen', 'Screen print');
        $screen->setPreselected(true);
        $field->addValue($screen);

        self::assertFalse($digital->isPreselected());
        self::assertTrue($screen->isPreselected());
    }

    public function testMultipleChoiceAllowsSeveralPreselectedValues(): void
    {
        $field = new ConfiguratorField('finishes', 'Finishes', FieldType::MULTIPLE_CHOICE);
        $first = new ConfiguratorValue('first', 'First');
        $second = new ConfiguratorValue('second', 'Second');
        $field->addValue($first);
        $field->addValue($second);

        $first->setPreselected(true);
        $second->setPreselected(true);

        self::assertTrue($first->isPreselected());
        self::assertTrue($second->isPreselected());
    }

    public function testDisabledValueCannotRemainOrBecomePreselected(): void
    {
        $value = new ConfiguratorValue('digital', 'Digital print');
        $value->setPreselected(true);
        $value->setEnabled(false);
        self::assertFalse($value->isPreselected());

        $value->setPreselected(true);
        self::assertFalse($value->isPreselected());
    }
}
