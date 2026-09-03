<?php

declare(strict_types=1);

namespace App\Twig;

use App\Maintenance\ProductMaintenanceOfferResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MaintenanceExtension extends AbstractExtension
{
    public function __construct(private readonly ProductMaintenanceOfferResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_maintenance_offers', $this->resolver->resolve(...))];
    }
}
