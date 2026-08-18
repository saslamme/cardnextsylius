<?php

declare(strict_types=1);

namespace App\Twig\Component;

use App\Entity\Configurator\Configurator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'cardnext:configurator', template: 'shop/configurator/product.html.twig')]
final class ConfiguratorProductComponent
{
    public Configurator $configurator;

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
