<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\Dto\Configurator\ConfiguratorConfiguration;
use App\Dto\Configurator\ConfiguratorPriceResult;
use App\Entity\Configurator\Configurator;
use App\Entity\Order\ConfiguredOrderItem;

final readonly class ConfiguredOrderItemSnapshotFactory
{
    public function __construct(private ConfigurationHashGenerator $hashGenerator)
    {
    }

    public function create(Configurator $configurator, ConfiguratorConfiguration $configuration, ConfiguratorPriceResult $price, string $locale): ConfiguredOrderItem
    {
        $snapshot = [];
        foreach ($configurator->getSections() as $section) {
            foreach ($section->getFields() as $field) {
                if (!array_key_exists($field->getCode(), $configuration->selections)) {
                    continue;
                }
                $selected = $configuration->selections[$field->getCode()];
                $entry = ['fieldCode' => $field->getCode(), 'fieldName' => $field->getName(), 'type' => $field->getType()->value];
                $values = [];
                foreach ($field->getValues() as $value) {
                    $values[$value->getCode()] = ['code' => $value->getCode(), 'name' => $value->getName()];
                }
                if ($field->getType()->value === 'multiple_choice') {
                    $entry['values'] = array_values(array_intersect_key($values, array_flip(is_array($selected) ? $selected : [])));
                } elseif ($field->getType()->value === 'single_choice') {
                    $entry['value'] = $values[(string) $selected] ?? ['code' => (string) $selected, 'name' => (string) $selected];
                } else {
                    $entry['value'] = $selected;
                }
                $snapshot[$field->getCode()] = $entry;
            }
        }

        $translation = $configurator->getTranslation($locale);
        $canonical = ['quantity' => $configuration->quantity, 'leadTimeCode' => $configuration->leadTimeCode, 'selections' => $configuration->selections];

        return new ConfiguredOrderItem($configurator->getCode(), $translation?->getName() ?? $configurator->getName(), $locale, $configuration->channelCode, $price->currencyCode, $price->quantity, $this->hashGenerator->generate($configuration), $snapshot, array_map(static fn ($line): array => $line->jsonSerialize(), $price->breakdown), $canonical, $price->baseUnitAmount, $price->optionsUnitAmount, $price->unitAmount, $price->unitTotal, $price->fixedTotal, $price->percentageTotal, $price->total, $price->leadTimeCode, $price->leadTimeName, $price->workingDays);
    }
}
