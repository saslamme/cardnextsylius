<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Dto\Configurator\ConfiguratorConfiguration;
use App\Entity\Channel\Channel;
use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorDependency;
use App\Entity\Configurator\ConfiguratorField;
use App\Entity\Configurator\ConfiguratorPriceRule;
use App\Entity\Configurator\ConfiguratorSection;
use App\Entity\Configurator\ConfiguratorValue;
use App\Enum\Configurator\DependencyEffect;
use App\Enum\Configurator\DependencyOperator;
use App\Enum\Configurator\FieldType;
use App\Enum\Configurator\MultiplierType;
use App\Enum\Configurator\PercentageBase;
use App\Enum\Configurator\PriceType;
use App\Exception\Configurator\AmbiguousPriceRuleException;
use App\Exception\Configurator\InvalidConfigurationException;
use App\Repository\Configurator\ConfiguratorPriceRuleRepository;
use App\Repository\Configurator\ConfiguratorRepository;
use App\Service\Configurator\ConfigurationHashGenerator;
use App\Service\Configurator\ConfiguratorPriceCalculator;
use App\Service\Configurator\ConfiguratorValidator;
use App\Service\Configurator\PriceRuleOverlapValidator;
use App\Service\Configurator\PriceRuleResolver;
use PHPUnit\Framework\TestCase;

final class ConfiguratorCoreTest extends TestCase
{
    public function testIndependentTiersUnitFixedAndIntegerTotals(): void
    {
        [$model,$field,$option] = $this->model();
        $cfg = new ConfiguratorConfiguration('generic', 500, 'EUR', 'DE_WEB', ['finish' => 'premium']);
        $rules = [$this->rule($model, null, PriceType::UNIT, 42, 500, 999), $this->rule($model, $option, PriceType::UNIT, 12, 250, 749), $this->rule($model, $option, PriceType::FIXED, 2900, 100, null, 'setup')];
        $result = $this->calculateWithRules($cfg, $model, $rules);
        self::assertSame(42, $result->baseUnitAmount);
        self::assertSame(12, $result->optionsUnitAmount);
        self::assertSame(54, $result->unitAmount);
        self::assertSame(27000, $result->unitTotal);
        self::assertSame(2900, $result->fixedTotal);
        self::assertSame(29900, $result->total);
        self::assertContainsOnly('integer', [$result->unitAmount, $result->total]);
    }

    public function testFieldValueMultiplierIsExact(): void
    {
        [$model,$field,$option] = $this->model();
        $count = new ConfiguratorField('count', 'Count', FieldType::INTEGER);
        $field->getSection()->addField($count);
        $setup = $this->rule($model, $option, PriceType::FIXED, 3500, 1, null, 'setup');
        $setup->setMultiplier(MultiplierType::FIELD_VALUE, $count);
        $base = $this->rule($model, null, PriceType::UNIT, 0);
        $result = $this->calculateWithRules(new ConfiguratorConfiguration('generic', 1000, 'EUR', 'DE_WEB', ['finish' => 'premium', 'count' => 3]), $model, [$base, $setup]);
        self::assertSame(10500, $result->fixedTotal);
    }

    public function testChannelOverrideAndGlobalFallbackAndCurrencyIsolation(): void
    {
        [$m,,$v] = $this->model();
        $global = $this->rule($m, $v, PriceType::UNIT, 12);
        $de = $this->rule($m, $v, PriceType::UNIT, 10);
        $de->setChannel($this->channel('DE_WEB'));
        $usd = $this->rule($m, $v, PriceType::UNIT, 99);
        $usd->setCurrencyCode('USD');
        $resolver = new PriceRuleResolver();
        self::assertSame(10, $resolver->resolve([$global, $de, $usd], 100, 'DE_WEB', 'EUR')[0]->getAmount());
        self::assertSame(12, $resolver->resolve([$global, $de, $usd], 100, 'AT_WEB', 'EUR')[0]->getAmount());
        self::assertSame(99, $resolver->resolve([$global, $de, $usd], 100, 'DE_WEB', 'USD')[0]->getAmount());
    }

    public function testDisabledRulesIgnoredAndAmbiguityRejected(): void
    {
        [$m,,$v] = $this->model();
        $a = $this->rule($m, $v, PriceType::UNIT, 12);
        $b = $this->rule($m, $v, PriceType::UNIT, 10);
        $b->setEnabled(false);
        self::assertSame([$a], (new PriceRuleResolver())->resolve([$a, $b], 100, 'DE_WEB', 'EUR'));
        $b->setEnabled(true);
        $this->expectException(AmbiguousPriceRuleException::class);
        (new PriceRuleResolver())->resolve([$a, $b], 100, 'DE_WEB', 'EUR');
    }

    public function testOverlapUsesFullDimensionIncludingChargeCode(): void
    {
        [$m,,$v] = $this->model();
        $a = $this->rule($m, $v, PriceType::UNIT, 1, 100, 500);
        $b = $this->rule($m, $v, PriceType::UNIT, 2, 400, 1000);
        $validator = new PriceRuleOverlapValidator();
        self::assertCount(1, $validator->findOverlaps([$a, $b]));
        $setup = $this->rule($m, $v, PriceType::UNIT, 2, 400, 1000, 'setup');
        self::assertCount(0, $validator->findOverlaps([$a, $setup]));
    }

    public function testDependenciesInvalidValueAndSingleChoiceValidation(): void
    {
        [$m,$field] = $this->model();
        $target = new ConfiguratorField('details', 'Details', FieldType::TEXT);
        $field->getSection()->addField($target);
        $require = new ConfiguratorDependency($m, $field, DependencyOperator::EQUALS, ['premium'], DependencyEffect::REQUIRE);
        $require->setTargetField($target);
        $result = (new ConfiguratorValidator())->validate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['finish' => ['premium', 'other']]), $m);
        $codes = array_map(fn ($e) => $e->errorCode, $result->errors);
        self::assertContains('single_choice_count', $codes);
        self::assertContains('invalid_value', $codes);
        $required = (new ConfiguratorValidator())->validate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['finish' => 'premium']), $m, [$require]);
        self::assertSame('dependency_required', $required->errors[0]->errorCode);
        $forbid = new ConfiguratorDependency($m, $field, DependencyOperator::EQUALS, ['premium'], DependencyEffect::FORBID);
        $forbid->setTargetField($target);
        $result = (new ConfiguratorValidator())->validate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['finish' => 'premium', 'details' => 'x']), $m, [$forbid]);
        self::assertSame('dependency_forbidden', $result->errors[0]->errorCode);
    }

    public function testCanonicalHashAndBasisPointRounding(): void
    {
        $a = new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['b' => 2, 'a' => ['y' => 1, 'x' => 2]], ['z' => 1, 'a' => 2]);
        $b = new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['a' => ['x' => 2, 'y' => 1], 'b' => 2], ['a' => 2, 'z' => 1]);
        self::assertSame((new ConfigurationHashGenerator())->generate($a), (new ConfigurationHashGenerator())->generate($b));
        self::assertSame((new ConfigurationHashGenerator())->generate($a), (new ConfigurationHashGenerator())->generate(new ConfiguratorConfiguration('generic', 1, 'eur', 'DE', ['a' => ['x' => 2, 'y' => 1], 'b' => 2], ['a' => 2, 'z' => 1])));
        self::assertSame(67, ConfiguratorPriceCalculator::basisPoints(333, 2000));
    }

    public function testUnitFieldValueAndQuantitySemantics(): void
    {
        [$model,$field] = $this->model();
        $count = new ConfiguratorField('color_count', 'Colors', FieldType::INTEGER);
        $field->getSection()->addField($count);
        $base = $this->rule($model, null, PriceType::UNIT, 10);
        $base->setMultiplier(MultiplierType::FIELD_VALUE, $count);
        $result = $this->calculateWithRules(new ConfiguratorConfiguration('generic', 1000, 'EUR', 'DE_WEB', ['color_count' => 3]), $model, [$base]);
        self::assertSame(30, $result->unitAmount);
        self::assertSame(30000, $result->total);

        $this->expectException(\DomainException::class);
        $base->setMultiplier(MultiplierType::QUANTITY);
    }

    public function testEqualValueCodesOnDifferentFieldsAreIndependentDimensions(): void
    {
        [$model,$field] = $this->model();
        $other = new ConfiguratorField('breakaway', 'Breakaway', FieldType::SINGLE_CHOICE);
        $field->getSection()->addField($other);
        $yesA = new ConfiguratorValue('yes', 'Yes');
        $yesB = new ConfiguratorValue('yes', 'Yes');
        $field->addValue($yesA);
        $other->addValue($yesB);
        $rules = [$this->rule($model, null, PriceType::UNIT, 1), $this->rule($model, $yesA, PriceType::UNIT, 2), $this->rule($model, $yesB, PriceType::UNIT, 3)];
        self::assertCount(3, (new PriceRuleResolver())->resolve($rules, 1, 'DE_WEB', 'EUR'));
    }

    public function testTargetValueForbidAndRequire(): void
    {
        [$model,$source] = $this->model();
        $target = new ConfiguratorField('double_sided', 'Double sided', FieldType::SINGLE_CHOICE);
        $source->getSection()->addField($target);
        $yes = new ConfiguratorValue('yes', 'Yes');
        $no = new ConfiguratorValue('no', 'No');
        $target->addValue($yes);
        $target->addValue($no);
        $forbid = new ConfiguratorDependency($model, $source, DependencyOperator::EQUALS, ['premium'], DependencyEffect::FORBID);
        $forbid->setTargetValue($yes);
        $validator = new ConfiguratorValidator();
        self::assertTrue($validator->validate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['finish' => 'premium', 'double_sided' => 'no']), $model, [$forbid])->isValid());
        self::assertSame('dependency_forbidden', $validator->validate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['finish' => 'premium', 'double_sided' => 'yes']), $model, [$forbid])->errors[0]->errorCode);

        $require = new ConfiguratorDependency($model, $source, DependencyOperator::EQUALS, ['premium'], DependencyEffect::REQUIRE);
        $require->setTargetValue($yes);
        self::assertSame('dependency_required', $validator->validate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['finish' => 'premium', 'double_sided' => 'no']), $model, [$require])->errors[0]->errorCode);
    }

    public function testCalculateLoadsDependenciesAndChecksSnapshotContext(): void
    {
        [$model,$source] = $this->model();
        $target = new ConfiguratorField('details', 'Details', FieldType::TEXT);
        $source->getSection()->addField($target);
        $dependency = new ConfiguratorDependency($model, $source, DependencyOperator::EQUALS, ['premium'], DependencyEffect::REQUIRE);
        $dependency->setTargetField($target);
        $configurators = $this->createMock(ConfiguratorRepository::class);
        $configurators->method('findEnabledByCode')->willReturn($model);
        $calculator = new ConfiguratorPriceCalculator($configurators, $this->createMock(ConfiguratorPriceRuleRepository::class), new ConfiguratorValidator(), new PriceRuleResolver());

        try {
            $calculator->calculate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE_WEB', ['finish' => 'premium']), $this->channel('DE_WEB'), 'EUR');
            self::fail('REQUIRE dependency was not loaded by calculate().');
        } catch (InvalidConfigurationException $exception) {
            self::assertStringContainsString('dependency_required', $exception->getMessage());
        }

        $forbid = new ConfiguratorDependency($model, $source, DependencyOperator::EQUALS, ['premium'], DependencyEffect::FORBID);
        $forbid->setTargetField($target);

        try {
            $calculator->calculate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE_WEB', ['finish' => 'premium', 'details' => 'text']), $this->channel('DE_WEB'), 'EUR');
            self::fail('FORBID dependency was not loaded by calculate().');
        } catch (InvalidConfigurationException $exception) {
            self::assertStringContainsString('dependency_forbidden', $exception->getMessage());
        }
    }

    public function testCalculateRejectsChannelAndCurrencyMismatch(): void
    {
        $calculator = $this->calculator();
        foreach ([['CH_WEB', 'EUR'], ['DE_WEB', 'CHF']] as [$channel, $currency]) {
            try {
                $calculator->calculate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE_WEB'), $this->channel($channel), $currency);
                self::fail('Mismatch was accepted.');
            } catch (InvalidConfigurationException) {
                self::assertTrue(true);
            }
        }
    }

    public function testDuplicateFieldCodesAndCrossConfiguratorRelationsAreRejected(): void
    {
        [$model,$field] = $this->model();
        $section = new ConfiguratorSection('other', 'Other');
        $model->addSection($section);
        $this->expectException(\DomainException::class);
        $section->addField(new ConfiguratorField($field->getCode(), 'Duplicate', FieldType::TEXT));
    }

    public function testPriceRuleRejectsForeignValue(): void
    {
        [$model] = $this->model();
        [,,$foreignValue] = $this->foreignModel();
        $this->expectException(\DomainException::class);
        $this->rule($model, $foreignValue, PriceType::UNIT, 1);
    }

    public function testAggregateSupportsBothConstructionOrders(): void
    {
        $earlySection = new ConfiguratorSection('early', 'Early');
        $earlyField = new ConfiguratorField('early_field', 'Early field', FieldType::TEXT);
        $earlySection->addField($earlyField);
        $earlyConfigurator = new Configurator('early', 'Early');
        $earlyConfigurator->addSection($earlySection);
        self::assertSame($earlySection, $earlyField->getSection());
        self::assertSame($earlyConfigurator, $earlyField->getConfigurator());

        $lateConfigurator = new Configurator('late', 'Late');
        $lateSection = new ConfiguratorSection('late', 'Late');
        $lateConfigurator->addSection($lateSection);
        $lateField = new ConfiguratorField('late_field', 'Late field', FieldType::TEXT);
        $lateSection->addField($lateField);
        self::assertSame($lateConfigurator, $lateField->getConfigurator());
    }

    public function testLateSectionAttachmentRejectsDuplicateFieldCode(): void
    {
        $configurator = new Configurator('duplicate', 'Duplicate');
        $a = new ConfiguratorSection('a', 'A');
        $a->addField(new ConfiguratorField('color', 'Color', FieldType::TEXT));
        $configurator->addSection($a);
        $b = new ConfiguratorSection('b', 'B');
        $b->addField(new ConfiguratorField('color', 'Color again', FieldType::TEXT));

        $this->expectException(\DomainException::class);
        $configurator->addSection($b);
    }

    public function testUnitBreakdownMultipliersRemainMathematicallyConsistent(): void
    {
        [$model, $field] = $this->model();
        $count = new ConfiguratorField('count', 'Count', FieldType::INTEGER);
        $field->getSection()->addField($count);
        $rule = $this->rule($model, null, PriceType::UNIT, 10);

        $rule->setMultiplier(MultiplierType::FIELD_VALUE, $count);
        $result = $this->calculateWithRules(new ConfiguratorConfiguration('generic', 1000, 'EUR', 'DE_WEB', ['count' => 3]), $model, [$rule]);
        self::assertSame(3000, $result->breakdown[0]->multiplier);
        self::assertSame(30000, $result->breakdown[0]->amount);
    }

    public function testPercentQuantityMultiplierIsRejected(): void
    {
        [$model] = $this->model();
        $rule = $this->rule($model, null, PriceType::PERCENT, 2000);

        $this->expectException(\DomainException::class);
        $rule->setMultiplier(MultiplierType::QUANTITY);
    }

    public function testChangingTargetFieldCannotInvalidateTargetValue(): void
    {
        [$model, $source] = $this->model();
        $a = new ConfiguratorField('a', 'A', FieldType::SINGLE_CHOICE);
        $b = new ConfiguratorField('b', 'B', FieldType::SINGLE_CHOICE);
        $source->getSection()->addField($a);
        $source->getSection()->addField($b);
        $yes = new ConfiguratorValue('yes', 'Yes');
        $a->addValue($yes);
        $dependency = new ConfiguratorDependency($model, $source, DependencyOperator::EQUALS, ['premium'], DependencyEffect::REQUIRE);
        $dependency->setTargetValue($yes);

        $this->expectException(\DomainException::class);
        $dependency->setTargetField($b);
    }

    /** @dataProvider invalidTypedSelections */
    public function testStrictFieldTypeValidation(FieldType $type, mixed $value, bool $valid): void
    {
        $model = new Configurator('typed', 'Typed');
        $section = new ConfiguratorSection('fields', 'Fields');
        $model->addSection($section);
        $field = new ConfiguratorField('value', 'Value', $type);
        $section->addField($field);

        self::assertSame($valid, (new ConfiguratorValidator())->validate(new ConfiguratorConfiguration('typed', 1, 'EUR', 'DE', ['value' => $value]), $model)->isValid());
    }

    public static function invalidTypedSelections(): iterable
    {
        yield 'integer decimal' => [FieldType::INTEGER, 1.5, false];
        yield 'canonical integer string' => [FieldType::INTEGER, '3', false];
        yield 'zero quantity' => [FieldType::QUANTITY, 0, false];
        yield 'boolean string' => [FieldType::BOOLEAN, 'yes', false];
        yield 'boolean' => [FieldType::BOOLEAN, true, true];
        yield 'text array' => [FieldType::TEXT, [], false];
    }

    public function testFieldValueMultiplierRejectsNonIntegerFieldTypes(): void
    {
        [$model, $field] = $this->model();
        $rule = $this->rule($model, null, PriceType::UNIT, 10);

        $this->expectException(\DomainException::class);
        $rule->setMultiplier(MultiplierType::FIELD_VALUE, $field);
    }

    /** @dataProvider multiplierMatrix */
    public function testCompleteMultiplierMatrix(PriceType $priceType, MultiplierType $multiplierType, bool $allowed): void
    {
        [$model, $field] = $this->model();
        $numeric = new ConfiguratorField('count', 'Count', FieldType::INTEGER);
        $field->getSection()->addField($numeric);
        $rule = $this->rule($model, null, $priceType, 100);

        try {
            $rule->setMultiplier($multiplierType, $multiplierType === MultiplierType::FIELD_VALUE ? $numeric : null);
            self::assertTrue($allowed);
        } catch (\DomainException) {
            self::assertFalse($allowed);
        }
    }

    public static function multiplierMatrix(): iterable
    {
        foreach (PriceType::cases() as $priceType) {
            foreach (MultiplierType::cases() as $multiplierType) {
                yield $priceType->value . ' ' . $multiplierType->value => [$priceType, $multiplierType, !($multiplierType === MultiplierType::QUANTITY && $priceType !== PriceType::FIXED)];
            }
        }
    }

    public function testFieldValueMultiplierAllowsZeroAndRejectsNegative(): void
    {
        [$model, $field] = $this->model();
        $count = new ConfiguratorField('count', 'Count', FieldType::INTEGER);
        $field->getSection()->addField($count);
        $rule = $this->rule($model, null, PriceType::UNIT, 10);
        $rule->setMultiplier(MultiplierType::FIELD_VALUE, $count);
        self::assertSame(0, $this->calculateWithRules(new ConfiguratorConfiguration('generic', 2, 'EUR', 'DE_WEB', ['count' => 0]), $model, [$rule])->total);
        $this->expectException(InvalidConfigurationException::class);
        $this->calculateWithRules(new ConfiguratorConfiguration('generic', 2, 'EUR', 'DE_WEB', ['count' => -1]), $model, [$rule]);
    }

    public function testPercentageBaseInvariantAndDefaults(): void
    {
        [$model] = $this->model();
        self::assertSame(PercentageBase::SUBTOTAL, $this->rule($model, null, PriceType::PERCENT, 100)->getPercentageBase());
        $unit = $this->rule($model, null, PriceType::UNIT, 100);
        self::assertNull($unit->getPercentageBase());
        $this->expectException(\DomainException::class);
        $unit->setPercentageBase(PercentageBase::BASE);
    }

    public function testFixedAndPercentBreakdownArithmetic(): void
    {
        [$model, $field] = $this->model();
        $count = new ConfiguratorField('count', 'Count', FieldType::INTEGER);
        $field->getSection()->addField($count);
        $base = $this->rule($model, null, PriceType::UNIT, 10);
        $fixed = $this->rule($model, null, PriceType::FIXED, 3500, 1, null, 'setup');
        $fixed->setMultiplier(MultiplierType::FIELD_VALUE, $count);
        $percent = $this->rule($model, null, PriceType::PERCENT, 2000, 1, null, 'surcharge');
        $result = $this->calculateWithRules(new ConfiguratorConfiguration('generic', 1000, 'EUR', 'DE_WEB', ['count' => 3]), $model, [$base, $fixed, $percent]);
        self::assertSame([null, 10, null], array_column($result->breakdown, 'unitAmount'));
        self::assertSame([null, null, 20500], array_column($result->breakdown, 'baseAmount'));
        self::assertSame([3, 1000, 1], array_column($result->breakdown, 'multiplier'));
        self::assertSame([10500, 10000, 4100], array_column($result->breakdown, 'amount'));
        self::assertSame(24600, $result->total);
    }

    public function testReparentingAndDuplicateValueCodesAreRejected(): void
    {
        [$first, $field] = $this->model();
        [$second] = $this->foreignModel();
        $section = $field->getSection();

        try {
            $second->addSection($section);
            self::fail('Section reparented.');
        } catch (\DomainException) {
            self::assertTrue(true);
        }
        $otherSection = new ConfiguratorSection('other', 'Other');
        $first->addSection($otherSection);

        try {
            $otherSection->addField($field);
            self::fail('Field reparented.');
        } catch (\DomainException) {
            self::assertTrue(true);
        }
        $value = $field->getValues()->first();
        $otherField = new ConfiguratorField('other', 'Other', FieldType::SINGLE_CHOICE);
        $otherSection->addField($otherField);

        try {
            $otherField->addValue($value);
            self::fail('Value reparented.');
        } catch (\DomainException) {
            self::assertTrue(true);
        }
        $this->expectException(\DomainException::class);
        $field->addValue(new ConfiguratorValue('premium', 'Duplicate'));
    }

    public function testNumericFieldConfigurationRejectsInvalidDefinitions(): void
    {
        $field = new ConfiguratorField('number', 'Number', FieldType::INTEGER);
        foreach (['abc', '1.5'] as $invalid) {
            try {
                $field->setMinimumValue($invalid);
                self::fail('Invalid minimum accepted.');
            } catch (\DomainException) {
                self::assertTrue(true);
            }
        }
        $field->setMinimumValue('1');
        $field->setMaximumValue('10');
        $field->setStep('1');
        self::assertSame('1', $field->getStep());
        $this->expectException(\DomainException::class);
        $field->setStep('0');
    }

    public function testDependencyExpectedValuesAndAbsentSourceAreSafe(): void
    {
        [$model, $source] = $this->model();
        $target = new ConfiguratorField('target', 'Target', FieldType::TEXT);
        $source->getSection()->addField($target);

        try {
            new ConfiguratorDependency($model, $source, DependencyOperator::GREATER_THAN, [], DependencyEffect::REQUIRE);
            self::fail('Empty comparison accepted.');
        } catch (\DomainException) {
            self::assertTrue(true);
        }
        $dependency = new ConfiguratorDependency($model, $source, DependencyOperator::NOT_EQUALS, ['premium'], DependencyEffect::REQUIRE);
        $dependency->setTargetField($target);
        self::assertTrue((new ConfiguratorValidator())->validate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE'), $model, [$dependency])->isValid());
    }

    public function testUpdatedAtChangesOnBusinessMutations(): void
    {
        [$model] = $this->model();
        $before = $model->getUpdatedAt();
        $model->setName('Changed');
        self::assertGreaterThan($before, $model->getUpdatedAt());

        $rule = $this->rule($model, null, PriceType::FIXED, 100);
        $before = $rule->getUpdatedAt();
        $rule->setAmount(200);
        self::assertGreaterThan($before, $rule->getUpdatedAt());
    }

    /** @param list<ConfiguratorPriceRule> $rules */
    private function calculateWithRules(ConfiguratorConfiguration $configuration, Configurator $model, array $rules): \App\Dto\Configurator\ConfiguratorPriceResult
    {
        $configurators = $this->createMock(ConfiguratorRepository::class);
        $configurators->method('findEnabledByCode')->willReturn($model);
        $repository = $this->createMock(ConfiguratorPriceRuleRepository::class);
        $repository->method('findApplicable')->willReturn($rules);
        $calculator = new ConfiguratorPriceCalculator($configurators, $repository, new ConfiguratorValidator(), new PriceRuleResolver());

        return $calculator->calculate($configuration, $this->channel($configuration->channelCode), $configuration->currencyCode);
    }

    private function foreignModel(): array
    {
        $m = new Configurator('foreign', 'Foreign');
        $s = new ConfiguratorSection('foreign', 'Foreign');
        $m->addSection($s);
        $f = new ConfiguratorField('foreign', 'Foreign', FieldType::SINGLE_CHOICE);
        $s->addField($f);
        $v = new ConfiguratorValue('yes', 'Yes');
        $f->addValue($v);

        return [$m, $f, $v];
    }

    private function model(): array
    {
        $m = new Configurator('generic', 'Generic');
        $s = new ConfiguratorSection('options', 'Options');
        $m->addSection($s);
        $f = new ConfiguratorField('finish', 'Finish', FieldType::SINGLE_CHOICE);
        $s->addField($f);
        $v = new ConfiguratorValue('premium', 'Premium');
        $f->addValue($v);

        return[$m, $f, $v];
    }

    private function rule(Configurator $m, ?ConfiguratorValue $v, PriceType $type, int $amount, int $min = 1, ?int $max = null, string $charge = 'production'): ConfiguratorPriceRule
    {
        $r = new ConfiguratorPriceRule($m, 'EUR', $charge, $type, $amount);
        $r->setValue($v);
        $r->setQuantityRange($min, $max);
        if ($type === PriceType::PERCENT) {
            $r->setPercentageBase(PercentageBase::SUBTOTAL);
        }

        return$r;
    }

    private function channel(string $code): Channel
    {
        $c = new Channel();
        $c->setCode($code);

        return$c;
    }

    private function calculator(): ConfiguratorPriceCalculator
    {
        return new ConfiguratorPriceCalculator($this->createMock(ConfiguratorRepository::class), $this->createMock(ConfiguratorPriceRuleRepository::class), new ConfiguratorValidator(), new PriceRuleResolver());
    }
}
