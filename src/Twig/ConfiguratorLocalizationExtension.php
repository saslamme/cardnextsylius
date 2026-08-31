<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Configurator\ConfiguratorField;
use App\Entity\Configurator\ConfiguratorLeadTime;
use App\Entity\Configurator\ConfiguratorSection;
use App\Entity\Configurator\ConfiguratorValue;
use App\Service\Configurator\ConfiguratorLocalizationResolver;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ConfiguratorLocalizationExtension extends AbstractExtension
{
    public function __construct(private readonly ConfiguratorLocalizationResolver $resolver, private readonly LocaleContextInterface $localeContext)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('configurator_text', $this->text(...))];
    }

    public function text(object $entity, string $property): ?string
    {
        $prefix = match (true) {
            $entity instanceof ConfiguratorSection => 'section',
            $entity instanceof ConfiguratorField => 'field',
            $entity instanceof ConfiguratorValue => 'value',
            $entity instanceof ConfiguratorLeadTime => 'leadTime',
            default => throw new \InvalidArgumentException('Unsupported configurator entity.'),
        };
        $method = $prefix . ucfirst($property);

        return $this->resolver->$method($entity, $this->localeContext->getLocaleCode());
    }
}
