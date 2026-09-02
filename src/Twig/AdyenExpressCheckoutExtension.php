<?php

declare(strict_types=1);

namespace App\Twig;

use App\Payment\AdyenExpressCheckoutAvailabilityInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdyenExpressCheckoutExtension extends AbstractExtension
{
    public function __construct(private readonly AdyenExpressCheckoutAvailabilityInterface $availability)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cardnext_adyen_express_checkout_available', $this->availability->isAvailable(...)),
        ];
    }
}
