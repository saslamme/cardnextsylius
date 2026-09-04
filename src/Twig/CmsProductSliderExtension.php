<?php

declare(strict_types=1);

namespace App\Twig;

use App\Cms\CmsProductSliderResolver;
use App\Entity\Product\Product;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CmsProductSliderExtension extends AbstractExtension
{
    public function __construct(private readonly CmsProductSliderResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_cms_product_slider_products', [$this, 'products'])];
    }

    /**
     * @param array<mixed> $codes
     *
     * @return list<Product>
     */
    public function products(array $codes, ?int $limit = 8): array
    {
        return $this->resolver->resolve($codes, $limit);
    }
}
