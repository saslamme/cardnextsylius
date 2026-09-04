<?php

declare(strict_types=1);

namespace App\Twig;

use App\Cms\CmsManufacturerSliderResolver;
use App\Entity\Product\Manufacturer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CmsManufacturerSliderExtension extends AbstractExtension
{
    public function __construct(private readonly CmsManufacturerSliderResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_cms_manufacturers', [$this, 'manufacturers'])];
    }

    /**
     * @param array<mixed> $codes
     *
     * @return list<Manufacturer>
     */
    public function manufacturers(array $codes, ?int $limit = 12): array
    {
        return $this->resolver->resolve($codes, $limit);
    }
}
