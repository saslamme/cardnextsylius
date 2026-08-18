<?php

declare(strict_types=1);

namespace App\Twig\Component;

use App\Entity\Configurator\Configurator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'cardnext:configurator', template: 'shop/configurator/product.html.twig')]
final class ConfiguratorProductComponent
{
    public Configurator $configurator;
}
