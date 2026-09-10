<?php

declare(strict_types=1);

namespace App\Twig;

use App\Payment\FooterPaymentMethodPresentationResolver;
use App\Payment\FooterPaymentMethodProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FooterPaymentMethodExtension extends AbstractExtension
{
    public function __construct(
        private readonly FooterPaymentMethodProvider $provider,
        private readonly FooterPaymentMethodPresentationResolver $presentationResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_footer_payment_methods', $this->paymentMethods(...))];
    }

    /**
     * @return list<array{code: string, label: string, icon: string|null}>
     */
    public function paymentMethods(): array
    {
        return array_map(
            fn (array $paymentMethod): array => $this->presentationResolver->resolve($paymentMethod['code'], $paymentMethod['label']),
            $this->provider->provide(),
        );
    }
}
