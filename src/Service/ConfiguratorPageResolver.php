<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Channel\Channel;
use App\Repository\Configurator\ConfiguratorRepository;

final readonly class ConfiguratorPageResolver
{
    public function __construct(private ConfiguratorRepository $configurators)
    {
    }

    // @phpstan-ignore missingType.iterableValue
    public function resolve(string $path, string $locale, Channel $channel): ?array
    {
        return $this->configurators->findPublicByPath($path, $locale, $channel);
    }
}
