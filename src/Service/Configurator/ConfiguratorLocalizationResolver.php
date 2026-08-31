<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\Entity\Configurator\ConfiguratorField;
use App\Entity\Configurator\ConfiguratorLeadTime;
use App\Entity\Configurator\ConfiguratorSection;
use App\Entity\Configurator\ConfiguratorValue;

final class ConfiguratorLocalizationResolver
{
    public function sectionName(ConfiguratorSection $section, string $locale): string
    {
        return $this->text($section, $locale, 'getName', $section->getName()) ?? $section->getName();
    }

    public function sectionDescription(ConfiguratorSection $section, string $locale): ?string
    {
        return $this->text($section, $locale, 'getDescription', $section->getDescription());
    }

    public function fieldName(ConfiguratorField $field, string $locale): string
    {
        return $this->text($field, $locale, 'getName', $field->getName()) ?? $field->getName();
    }

    public function fieldDescription(ConfiguratorField $field, string $locale): ?string
    {
        return $this->text($field, $locale, 'getDescription', $field->getDescription());
    }

    public function fieldHelpText(ConfiguratorField $field, string $locale): ?string
    {
        return $this->text($field, $locale, 'getHelpText', $field->getHelpText());
    }

    public function valueName(ConfiguratorValue $value, string $locale): string
    {
        return $this->text($value, $locale, 'getName', $value->getName()) ?? $value->getName();
    }

    public function valueDescription(ConfiguratorValue $value, string $locale): ?string
    {
        return $this->text($value, $locale, 'getDescription', $value->getDescription());
    }

    public function leadTimeName(ConfiguratorLeadTime $leadTime, string $locale): string
    {
        return $this->text($leadTime, $locale, 'getName', $leadTime->getName()) ?? $leadTime->getName();
    }

    public function leadTimeDescription(ConfiguratorLeadTime $leadTime, string $locale): ?string
    {
        return $this->text($leadTime, $locale, 'getDescription', $leadTime->getDescription());
    }

    /** @param ConfiguratorSection|ConfiguratorField|ConfiguratorValue|ConfiguratorLeadTime $entity */
    private function text(object $entity, string $locale, string $getter, ?string $legacy): ?string
    {
        foreach (array_values(array_unique([$locale, 'de_DE'])) as $candidate) {
            $translation = $entity->getTranslation($candidate);
            if ($translation !== null) {
                $value = $translation->$getter();
                if ($value !== null && trim($value) !== '') {
                    return $value;
                }
            }
        }

        return $legacy;
    }
}
