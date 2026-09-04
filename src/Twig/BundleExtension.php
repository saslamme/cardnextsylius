<?php

declare(strict_types=1);

namespace App\Twig;

use App\Bundle\BundleViewResolver;
use App\Entity\Product\Product;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BundleExtension extends AbstractExtension
{
    public function __construct(private readonly BundleViewResolver $resolver) {}
    public function getFunctions(): array { return [new TwigFunction('cardnext_product_bundles', $this->bundles(...))]; }
    /** @return list<array<string, mixed>> */
    public function bundles(?Product $product): array
    {
        if (!$product instanceof Product) {
            return [];
        }

        return $this->resolver->forProduct($product);
    }
}
