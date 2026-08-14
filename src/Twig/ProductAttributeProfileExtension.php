<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product\Product;
use App\Service\ProductAttributeProfileService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProductAttributeProfileExtension extends AbstractExtension
{
    public function __construct(private readonly ProductAttributeProfileService $profiles)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cardnext_product_profile_code', $this->profileCode(...)),
            new TwigFunction('cardnext_product_profile_label', $this->profileLabel(...)),
            new TwigFunction('cardnext_product_profile_definitions', $this->profileDefinitions(...)),
        ];
    }

    public function profileCode(Product $product): ?string
    {
        return $this->profiles->resolveProfileCode($product);
    }

    public function profileLabel(Product $product): ?string
    {
        return $this->profiles->getProfileLabel($this->profiles->resolveProfileCode($product));
    }

    /**
     * @return array<string, array{name: string, type: string, position: int}>
     */
    public function profileDefinitions(Product $product): array
    {
        $code = $this->profiles->resolveProfileCode($product);

        return $code !== null ? $this->profiles->getDefinitionsForProfile($code) : [];
    }
}
