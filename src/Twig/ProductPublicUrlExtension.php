<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product\Product;
use App\Service\ProductPublicUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProductPublicUrlExtension extends AbstractExtension
{
    public function __construct(private readonly ProductPublicUrlGenerator $generator)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_product_url', $this->url(...))];
    }

    public function url(Product $product): string
    {
        return $this->generator->generate($product);
    }
}
