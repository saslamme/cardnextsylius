<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorDependency;
use App\Enum\Configurator\DependencyEffect;
use App\Enum\Configurator\DependencyOperator;

final class DependencyStateResolver
{
    /** @param array<string, mixed> $selections @param iterable<ConfiguratorDependency> $dependencies
     *  @return array<string, array{visible: bool, enabled: bool, required: bool, values: array<string, array{visible: bool, enabled: bool}>}>
     */
    public function resolve(Configurator $configurator, array $selections, iterable $dependencies): array
    {
        $state = [];
        foreach ($configurator->getSections() as $section) {
            foreach ($section->getFields() as $field) {
                $state[$field->getCode()] = ['visible' => $section->isEnabled() && $field->isEnabled(), 'enabled' => $section->isEnabled() && $field->isEnabled(), 'required' => $field->isRequired(), 'values' => []];
                foreach ($field->getValues() as $value) {
                    $state[$field->getCode()]['values'][$value->getCode()] = ['visible' => $value->isEnabled(), 'enabled' => $value->isEnabled()];
                }
            }
        }
        $rules = array_values(array_filter(iterator_to_array((static function () use ($dependencies): \Generator { yield from $dependencies; })()), static fn ($d) => $d->isEnabled()));
        usort($rules, static fn ($a, $b) => $a->getPriority() <=> $b->getPriority() ?: (($a->getId() ?? 0) <=> ($b->getId() ?? 0)));

        // SHOW and ENABLE express gates: without a matching rule their target starts inactive.
        foreach ($rules as $rule) {
            if (in_array($rule->getEffect(), [DependencyEffect::SHOW, DependencyEffect::ENABLE], true)) {
                $this->apply($state, $rule, false, $rule->getEffect());
            }
        }
        foreach ($rules as $rule) {
            if ($this->matches($rule, $selections)) {
                $this->apply($state, $rule, true, $rule->getEffect());
            }
        }
        foreach ($state as &$field) {
            if (!$field['visible'] || !$field['enabled']) {
                $field['required'] = false;
            }
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function apply(array &$state, ConfiguratorDependency $rule, bool $active, DependencyEffect $effect): void
    {
        $fieldCode = $rule->getTargetField()?->getCode();
        if ($fieldCode === null || !isset($state[$fieldCode])) {
            return;
        }
        $target = &$state[$fieldCode];
        $valueCode = $rule->getTargetValue()?->getCode();
        if ($valueCode !== null && isset($target['values'][$valueCode])) {
            $target = &$target['values'][$valueCode];
        }
        match ($effect) {
            DependencyEffect::SHOW => $target['visible'] = $active,
            DependencyEffect::HIDE => $target['visible'] = !$active,
            DependencyEffect::ENABLE => $target['enabled'] = $active,
            DependencyEffect::DISABLE, DependencyEffect::FORBID => $target['enabled'] = !$active,
            DependencyEffect::REQUIRE => $state[$fieldCode]['required'] = $active,
        };
    }

    /** @param array<string, mixed> $selections */
    public function matches(ConfiguratorDependency $rule, array $selections): bool
    {
        $actual = $selections[$rule->getSourceField()->getCode()] ?? null;
        if ($actual === null || $actual === '' || $actual === []) {
            return false;
        }
        $expected = $rule->getExpectedValues();

        return match ($rule->getOperator()) {
            DependencyOperator::EQUALS => in_array($actual, $expected, true),
            DependencyOperator::NOT_EQUALS => !in_array($actual, $expected, true),
            DependencyOperator::IN => array_intersect((array) $actual, $expected) !== [],
            DependencyOperator::NOT_IN => array_intersect((array) $actual, $expected) === [],
            DependencyOperator::GREATER_THAN => is_numeric($actual) && (float) $actual > (float) $expected[0],
            DependencyOperator::GREATER_THAN_OR_EQUAL => is_numeric($actual) && (float) $actual >= (float) $expected[0],
            DependencyOperator::LESS_THAN => is_numeric($actual) && (float) $actual < (float) $expected[0],
            DependencyOperator::LESS_THAN_OR_EQUAL => is_numeric($actual) && (float) $actual <= (float) $expected[0],
            DependencyOperator::IS_SELECTED => true,
        };
    }
}
