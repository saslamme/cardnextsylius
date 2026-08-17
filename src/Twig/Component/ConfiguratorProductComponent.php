<?php

declare(strict_types=1);

namespace App\Twig\Component;

use App\Entity\Configurator\Configurator;
use App\Entity\Product\Product;
use App\Repository\Configurator\ConfiguratorRepository;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent(name: 'cardnext:configurator:product', template: 'shop/configurator/product.html.twig')]
final class ConfiguratorProductComponent
{
    public ProductInterface $product;

    #[ExposeInTemplate]
    public ?Configurator $configurator = null;

    public function __construct(private readonly ConfiguratorRepository $configurators)
    {
    }

    #[PostMount]
    public function postMount(): void
    {
        if ($this->product instanceof Product && $this->product->isConfigurable()) {
            $this->configurator = $this->configurators->findEnabledByProduct($this->product);
        }
    }
}
