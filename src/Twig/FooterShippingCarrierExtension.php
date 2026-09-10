<?php

declare(strict_types=1);

namespace App\Twig;

use App\Shipping\FooterShippingCarrierProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FooterShippingCarrierExtension extends AbstractExtension
{
    public function __construct(private readonly FooterShippingCarrierProvider $provider)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_footer_shipping_carriers', $this->provider->provide(...))];
    }
}
