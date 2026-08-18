<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\Dto\Configurator\ConfiguratorConfiguration;
use App\Dto\Configurator\ValidationError;
use App\Dto\Configurator\ValidationResult;
use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorDependency;
use App\Entity\Configurator\ConfiguratorField;
use App\Enum\Configurator\FieldType;

final class ConfiguratorValidator
{
    public function __construct(private readonly ?DependencyStateResolver $dependencyStateResolver = null)
    {
    }

    /** @param iterable<ConfiguratorDependency> $dependencies */
    public function validate(ConfiguratorConfiguration $cfg, Configurator $model, iterable $dependencies = []): ValidationResult
    {
        $errors = [];
        if (!$model->isEnabled() || $model->getCode() !== $cfg->configuratorCode) {
            $errors[] = new ValidationError(null, 'configurator_unavailable', 'Configurator does not exist or is disabled.');
        }if ($cfg->quantity < 1) {
            $errors[] = new ValidationError('quantity', 'invalid_quantity', 'Quantity must be at least one.');
        }
        $dependencyList = is_array($dependencies) ? $dependencies : iterator_to_array((static function () use ($dependencies): \Generator { yield from $dependencies; })());
        $state = ($this->dependencyStateResolver ?? new DependencyStateResolver())->resolve($model, $cfg->selections, $dependencyList);
        $enabledLeadTimes = array_values(array_filter($model->getLeadTimes()->toArray(), static fn ($leadTime) => $leadTime->isEnabled()));
        if ($enabledLeadTimes !== []) {
            $leadTime = array_values(array_filter($enabledLeadTimes, static fn ($candidate) => $candidate->getCode() === $cfg->leadTimeCode));
            if ($leadTime === []) {
                $errors[] = new ValidationError('leadTime', 'invalid_lead_time', 'An enabled production lead time must be selected.');
            }
        } elseif ($cfg->leadTimeCode !== null) {
            $errors[] = new ValidationError('leadTime', 'invalid_lead_time', 'Lead time does not belong to the configurator or is disabled.');
        }
        $fields = [];
        foreach ($model->getSections() as $section) {
            foreach ($section->getFields() as $field) {
                $fields[$field->getCode()] = [$field, $section->isEnabled()];
            }
        }foreach ($cfg->selections as $code => $selection) {
            if (!isset($fields[$code])) {
                $errors[] = new ValidationError($code, 'unknown_field', 'Field does not belong to the configurator.');

                continue;
            }[$field,$sectionEnabled] = $fields[$code];
            if (!$sectionEnabled || !$field->isEnabled()) {
                $errors[] = new ValidationError($code, 'field_disabled', 'Field or section is disabled.');

                continue;
            }
            $fieldState = $state[$code];
            if (!$fieldState['visible'] || !$fieldState['enabled']) {
                $forbidden = false;
                foreach ($dependencyList as $dependency) {
                    if ($dependency->isEnabled() && $dependency->getEffect()->value === 'forbid' && $dependency->getTargetField()?->getCode() === $code && $this->dependencyStateResolverOrDefault()->matches($dependency, $cfg->selections)) {
                        $forbidden = true;
                    }
                }
                $errors[] = new ValidationError($code, $forbidden ? 'dependency_forbidden' : 'dependency_unavailable', $forbidden ? 'Selection is forbidden by a dependency.' : 'Field is hidden or disabled by a dependency.');

                continue;
            }
            $this->validateSelection($field, $selection, $errors);
            foreach ((array) $selection as $selectedValue) {
                $valueState = is_string($selectedValue) ? ($fieldState['values'][$selectedValue] ?? null) : null;
                if ($valueState !== null && (!$valueState['visible'] || !$valueState['enabled'])) {
                    $errors[] = new ValidationError($code, 'dependency_forbidden', 'Value is hidden, disabled, or forbidden by a dependency.', ['value' => $selectedValue]);
                }
            }
        }foreach ($fields as $code => [$field,$sectionEnabled]) {
            if ($sectionEnabled && $field->isEnabled() && $state[$code]['required'] && !$this->hasSelection($cfg, $code)) {
                $dependencyRequired = false;
                foreach ($dependencyList as $dependency) {
                    if ($dependency->isEnabled() && $dependency->getEffect()->value === 'require' && $dependency->getTargetField()?->getCode() === $code && $this->dependencyStateResolverOrDefault()->matches($dependency, $cfg->selections)) {
                        $dependencyRequired = true;
                    }
                }
                $errors[] = new ValidationError($code, $dependencyRequired ? 'dependency_required' : 'required', $dependencyRequired ? 'Field is required by a dependency.' : 'A selection is required.');
            }
        }
        foreach ($dependencyList as $dependency) {
            if (!$dependency->isEnabled() || !$this->dependencyStateResolverOrDefault()->matches($dependency, $cfg->selections) || $dependency->getEffect()->value !== 'require' || $dependency->getTargetValue() === null) {
                continue;
            }
            $code = $dependency->getTargetField()?->getCode();
            if ($code !== null && !in_array($dependency->getTargetValue()->getCode(), (array) ($cfg->selections[$code] ?? []), true)) {
                $errors[] = new ValidationError($code, 'dependency_required', 'Specific value is required by a dependency.');
            }
        }

        return new ValidationResult($errors);
    }

    /** @param list<ValidationError> $errors */
    private function validateSelection(ConfiguratorField $field, mixed $selection, array &$errors): void
    {
        $type = $field->getType();
        if ($type === FieldType::SINGLE_CHOICE && !is_string($selection)) {
            $errors[] = new ValidationError($field->getCode(), 'single_choice_count', 'Single choice accepts exactly one value code.');
            if (!is_array($selection)) {
                return;
            }
        }
        if ($type === FieldType::MULTIPLE_CHOICE && (!is_array($selection) || !array_is_list($selection))) {
            $errors[] = new ValidationError($field->getCode(), 'multiple_choice_list', 'Multiple choice requires a list of value codes.');

            return;
        }
        if ($type === FieldType::MULTIPLE_CHOICE && count($selection) !== count(array_unique($selection, \SORT_REGULAR))) {
            $errors[] = new ValidationError($field->getCode(), 'duplicate_value', 'Multiple choice must not contain duplicate values.');
        }
        if (in_array($type, [FieldType::SINGLE_CHOICE, FieldType::MULTIPLE_CHOICE], true)) {
            $codes = $type === FieldType::SINGLE_CHOICE && !is_array($selection) ? [$selection] : $selection;
            foreach ($codes as $code) {
                $valid = false;
                foreach (is_string($code) ? $field->getValues() : [] as $value) {
                    if ($value->getCode() === $code && $value->isEnabled()) {
                        $valid = true;

                        break;
                    }
                }
                if (!$valid) {
                    $errors[] = new ValidationError($field->getCode(), 'invalid_value', 'Value does not belong to the field or is disabled.', ['value' => $code]);
                }
            }

            return;
        }
        if ($type === FieldType::BOOLEAN) {
            if (!is_bool($selection)) {
                $errors[] = new ValidationError($field->getCode(), 'not_boolean', 'A boolean value is required.');
            }

            return;
        }
        if ($type === FieldType::TEXT) {
            if (!is_string($selection)) {
                $errors[] = new ValidationError($field->getCode(), 'not_text', 'A string value is required.');
            }

            return;
        }
        if (in_array($type, [FieldType::INTEGER, FieldType::QUANTITY], true)) {
            if (!is_int($selection)) {
                $errors[] = new ValidationError($field->getCode(), 'not_integer', 'An integer value is required.');

                return;
            }
            if ($type === FieldType::QUANTITY && (int) $selection < 1) {
                $errors[] = new ValidationError($field->getCode(), 'not_positive', 'Quantity must be a positive integer.');

                return;
            }
            $this->validateNumericConstraints($field, (float) $selection, $errors);

            return;
        }
        if ($type === FieldType::DECIMAL) {
            if (!is_int($selection) && !is_float($selection) && !(is_string($selection) && is_numeric($selection))) {
                $errors[] = new ValidationError($field->getCode(), 'not_numeric', 'A numeric value is required.');

                return;
            }
            $this->validateNumericConstraints($field, (float) $selection, $errors);
        }
    }

    /** @param list<ValidationError> $errors */
    private function validateNumericConstraints(ConfiguratorField $field, float $value, array &$errors): void
    {
        $min = $field->getMinimumValue();
        $max = $field->getMaximumValue();
        $step = $field->getStep();
        if ($min !== null && $value < (float) $min) {
            $errors[] = new ValidationError($field->getCode(), 'below_minimum', 'Value is below its minimum.');
        }
        if ($max !== null && $value > (float) $max) {
            $errors[] = new ValidationError($field->getCode(), 'above_maximum', 'Value is above its maximum.');
        }
        if ($step !== null && (float) $step > 0.0) {
            $origin = $min === null ? 0.0 : (float) $min;
            if (abs(fmod($value - $origin, (float) $step)) > 1e-9) {
                $errors[] = new ValidationError($field->getCode(), 'invalid_step', 'Value does not match the configured step.');
            }
        }
    }

    private function hasSelection(ConfiguratorConfiguration $c, string $code): bool
    {
        return array_key_exists($code, $c->selections) && $c->selections[$code] !== null && $c->selections[$code] !== '' && $c->selections[$code] !== [];
    }

    private function dependencyStateResolverOrDefault(): DependencyStateResolver
    {
        return $this->dependencyStateResolver ?? new DependencyStateResolver();
    }
}
