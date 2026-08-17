<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\Dto\Configurator\ConfiguratorConfiguration;

final class ConfigurationHashGenerator
{
    public function generate(ConfiguratorConfiguration $configuration): string
    {
        return hash('sha256', json_encode($this->canonicalize($configuration->jsonSerialize()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }if (array_is_list($value)) {
            return array_map($this->canonicalize(...), $value);
        }ksort($value, SORT_STRING);
        foreach ($value as &$item) {
            $item = $this->canonicalize($item);
        }

        return $value;
    }
}
