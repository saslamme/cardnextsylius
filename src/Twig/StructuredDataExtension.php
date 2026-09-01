<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product\Product;
use App\Seo\StructuredData\StructuredDataBuilder;
use App\Seo\StructuredData\StructuredDataEncoder;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StructuredDataExtension extends AbstractExtension
{
    public function __construct(private readonly StructuredDataBuilder $builder, private readonly StructuredDataEncoder $encoder, private readonly RequestStack $requests) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cardnext_homepage_structured_data', $this->homepage(...)),
            new TwigFunction('cardnext_product_structured_data', $this->product(...)),
            new TwigFunction('cardnext_taxon_structured_data', $this->taxon(...)),
        ];
    }

    public function homepage(): string
    {
        $request = $this->requests->getCurrentRequest();
        return $this->encoder->encode($request === null ? null : $this->builder->homepage($request));
    }

    public function product(Product $product): string
    {
        $request = $this->requests->getCurrentRequest();
        return $this->encoder->encode($request === null ? null : $this->builder->product($request, $product));
    }

    public function taxon(TaxonInterface $taxon): string
    {
        $request = $this->requests->getCurrentRequest();
        return $this->encoder->encode($request === null ? null : $this->builder->taxon($request, $taxon));
    }
}
