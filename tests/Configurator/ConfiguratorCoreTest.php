<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Dto\Configurator\ConfiguratorConfiguration;
use App\Entity\Channel\Channel;
use App\Entity\Configurator\{Configurator,ConfiguratorDependency,ConfiguratorField,ConfiguratorPriceRule,ConfiguratorSection,ConfiguratorValue};
use App\Enum\Configurator\{DependencyEffect,DependencyOperator,FieldType,MultiplierType,PercentageBase,PriceType};
use App\Exception\Configurator\AmbiguousPriceRuleException;
use App\Exception\Configurator\InvalidConfigurationException;
use App\Repository\Configurator\{ConfiguratorPriceRuleRepository,ConfiguratorRepository};
use App\Service\Configurator\{ConfigurationHashGenerator,ConfiguratorPriceCalculator,ConfiguratorValidator,PriceRuleOverlapValidator,PriceRuleResolver};
use PHPUnit\Framework\TestCase;

final class ConfiguratorCoreTest extends TestCase
{
    public function testIndependentTiersUnitFixedAndIntegerTotals(): void
    {
        [$model,$field,$option] = $this->model();
        $cfg = new ConfiguratorConfiguration('generic', 500, 'EUR', 'DE_WEB', ['finish' => 'premium']);
        $rules = [$this->rule($model, null, PriceType::UNIT, 42, 500, 999),$this->rule($model, $option, PriceType::UNIT, 12, 250, 749),$this->rule($model, $option, PriceType::FIXED, 2900, 100, null, 'setup')];
        $result = $this->calculator()->calculateRules($cfg, $model, [$option], $rules, 'DE_WEB', 'EUR');
        self::assertSame(42, $result->baseUnitAmount);
        self::assertSame(12, $result->optionsUnitAmount);
        self::assertSame(54, $result->unitAmount);
        self::assertSame(27000, $result->unitTotal);
        self::assertSame(2900, $result->fixedTotal);
        self::assertSame(29900, $result->total);
        self::assertContainsOnly('integer', [$result->unitAmount,$result->total]);
    }
    public function testFieldValueMultiplierIsExact(): void
    {
        [$model,$field,$option] = $this->model();
        $count = new ConfiguratorField('count', 'Count', FieldType::INTEGER);
        $field->getSection()->addField($count);
        $setup = $this->rule($model, $option, PriceType::FIXED, 3500, 1, null, 'setup');
        $setup->setMultiplier(MultiplierType::FIELD_VALUE, $count);
        $base = $this->rule($model, null, PriceType::UNIT, 0);
        $result = $this->calculator()->calculateRules(new ConfiguratorConfiguration('generic', 1000, 'EUR', 'DE_WEB', ['finish' => 'premium','count' => 3]), $model, [$option], [$base,$setup], 'DE_WEB', 'EUR');
        self::assertSame(10500, $result->fixedTotal);
    }
    public function testChannelOverrideAndGlobalFallbackAndCurrencyIsolation(): void
    {
        [$m,,$v] = $this->model();
        $global = $this->rule($m, $v, PriceType::UNIT, 12);
        $de = $this->rule($m, $v, PriceType::UNIT, 10);
        $de->setChannel($this->channel('DE_WEB'));
        $usd = $this->rule($m, $v, PriceType::UNIT, 99);
        $ref = new \ReflectionProperty($usd, 'currencyCode');
        $ref->setValue($usd, 'USD');
        $resolver = new PriceRuleResolver();
        self::assertSame(10, $resolver->resolve([$global,$de,$usd], 100, 'DE_WEB', 'EUR')[0]->getAmount());
        self::assertSame(12, $resolver->resolve([$global,$de,$usd], 100, 'AT_WEB', 'EUR')[0]->getAmount());
        self::assertSame(99, $resolver->resolve([$global,$de,$usd], 100, 'DE_WEB', 'USD')[0]->getAmount());
    }
    public function testDisabledRulesIgnoredAndAmbiguityRejected(): void
    {
        [$m,,$v] = $this->model();
        $a = $this->rule($m, $v, PriceType::UNIT, 12);
        $b = $this->rule($m, $v, PriceType::UNIT, 10);
        $b->setEnabled(false);
        self::assertSame([$a], (new PriceRuleResolver())->resolve([$a,$b], 100, 'DE_WEB', 'EUR'));
        $b->setEnabled(true);
        $this->expectException(AmbiguousPriceRuleException::class);
        (new PriceRuleResolver())->resolve([$a,$b], 100, 'DE_WEB', 'EUR');
    }
    public function testOverlapUsesFullDimensionIncludingChargeCode(): void
    {
        [$m,,$v] = $this->model();
        $a = $this->rule($m, $v, PriceType::UNIT, 1, 100, 500);
        $b = $this->rule($m, $v, PriceType::UNIT, 2, 400, 1000);
        $validator = new PriceRuleOverlapValidator();
        self::assertCount(1, $validator->findOverlaps([$a,$b]));
        $setup = $this->rule($m, $v, PriceType::UNIT, 2, 400, 1000, 'setup');
        self::assertCount(0, $validator->findOverlaps([$a,$setup]));
    }
    public function testDependenciesInvalidValueAndSingleChoiceValidation(): void
    {
        [$m,$field] = $this->model();
        $target = new ConfiguratorField('details', 'Details', FieldType::TEXT);
        $field->getSection()->addField($target);
        $require = new ConfiguratorDependency($m, $field, DependencyOperator::EQUALS, ['premium'], DependencyEffect::REQUIRE);
        $require->setTargetField($target);
        $result = (new ConfiguratorValidator())->validate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['finish' => ['premium','other']]), $m);
        $codes = array_map(fn ($e) => $e->errorCode, $result->errors);
        self::assertContains('single_choice_count', $codes);
        self::assertContains('invalid_value', $codes);
        $required = (new ConfiguratorValidator())->validate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['finish' => 'premium']), $m, [$require]);
        self::assertSame('dependency_required', $required->errors[0]->errorCode);
        $forbid = new ConfiguratorDependency($m, $field, DependencyOperator::EQUALS, ['premium'], DependencyEffect::FORBID);
        $forbid->setTargetField($target);
        $result = (new ConfiguratorValidator())->validate(new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['finish' => 'premium','details' => 'x']), $m, [$forbid]);
        self::assertSame('dependency_forbidden', $result->errors[0]->errorCode);
    }
    public function testCanonicalHashAndBasisPointRounding(): void
    {
        $a = new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['b' => 2,'a' => ['y' => 1,'x' => 2]], ['z' => 1,'a' => 2]);
        $b = new ConfiguratorConfiguration('generic', 1, 'EUR', 'DE', ['a' => ['x' => 2,'y' => 1],'b' => 2], ['a' => 2,'z' => 1]);
        self::assertSame((new ConfigurationHashGenerator())->generate($a), (new ConfigurationHashGenerator())->generate($b));
        self::assertSame(67, ConfiguratorPriceCalculator::basisPoints(333, 2000));
    }
    public function testUnitFieldValueAndQuantitySemantics(): void
    {
        [$model,$field] = $this->model();
        $count = new ConfiguratorField('color_count', 'Colors', FieldType::INTEGER);
        $field->getSection()->addField($count);
        $base = $this->rule($model, null, PriceType::UNIT, 10);
        $base->setMultiplier(MultiplierType::FIELD_VALUE, $count);
        $result = $this->calculator()->calculateRules(new ConfiguratorConfiguration('generic', 1000, 'EUR', 'DE_WEB', ['color_count' => 3]), $model, [], [$base], 'DE_WEB', 'EUR');
        self::assertSame(30, $result->unitAmount);
        self::assertSame(30000, $result->total);

        $base->setMultiplier(MultiplierType::QUANTITY);
        $result = $this->calculator()->calculateRules(new ConfiguratorConfiguration('generic', 1000, 'EUR', 'DE_WEB'), $model, [], [$base], 'DE_WEB', 'EUR');
        self::assertSame(10000, $result->total);
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
        return[$m,$f,$v];
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
