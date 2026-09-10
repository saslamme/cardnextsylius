<?php

declare(strict_types=1);

namespace App\Twig;

use App\Payment\FooterPaymentMethodProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FooterPaymentMethodExtension extends AbstractExtension
{
    public function __construct(private readonly FooterPaymentMethodProvider $provider)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_footer_payment_methods', $this->provider->provide(...))];
    }
}
