<?php

declare(strict_types=1);

namespace App\Twig\Component;

use App\Entity\Configurator\Configurator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'cardnext:configurator', template: 'shop/configurator/product.html.twig')]
final class ConfiguratorProductComponent
{
    public Configurator $configurator;

    /** @var array{quantity: int, selections: array<string, mixed>, leadTimeCode: ?string}|null */
    public ?array $initialConfiguration = null;

    public function hasUnavailableSavedOptions(): bool
    {
        if ($this->initialConfiguration === null) {
            return false;
        }
        $fields = [];
        foreach ($this->configurator->getSections() as $section) {
            if (!$section->isEnabled()) {
                continue;
            }
            foreach ($section->getFields() as $field) {
                if ($field->isEnabled()) {
                    $fields[$field->getCode()] = $field;
                }
            }
        }
        foreach ($this->initialConfiguration['selections'] as $code => $selection) {
            $field = $fields[$code] ?? null;
            if ($field === null) {
                return true;
            }
            if (!in_array($field->getType()->value, ['single_choice', 'multiple_choice'], true)) {
                continue;
            }
            $available = [];
            foreach ($field->getValues() as $value) {
                if ($value->isEnabled()) {
                    $available[] = $value->getCode();
                }
            }
            foreach (is_array($selection) ? $selection : [$selection] as $selectedCode) {
                if (!in_array($selectedCode, $available, true)) {
                    return true;
                }
            }
        }
        if ($this->initialConfiguration['leadTimeCode'] !== null) {
            foreach ($this->configurator->getLeadTimes() as $leadTime) {
                if ($leadTime->isEnabled() && $leadTime->getCode() === $this->initialConfiguration['leadTimeCode']) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    public function getDependencies(): array
    {
        $result = [];
        foreach ($this->configurator->getDependencies() as $dependency) {
            if (!$dependency->isEnabled()) {
                continue;
            }
            $result[] = ['sourceFieldCode' => $dependency->getSourceField()->getCode(), 'operator' => $dependency->getOperator()->value, 'expectedValues' => $dependency->getExpectedValues(), 'effect' => $dependency->getEffect()->value, 'targetFieldCode' => $dependency->getTargetField()?->getCode(), 'targetValueCode' => $dependency->getTargetValue()?->getCode(), 'priority' => $dependency->getPriority()];
        }
        usort($result, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $result;
    }
}
