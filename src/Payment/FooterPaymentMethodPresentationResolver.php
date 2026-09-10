<?php

declare(strict_types=1);

namespace App\Payment;

final class FooterPaymentMethodPresentationResolver
{
    private const ICONS = [
        'visa' => 'payment-methods/visa.svg',
        'mastercard' => 'payment-methods/mastercard.svg',
        'paypal' => 'payment-methods/paypal.svg',
        'applepay' => 'payment-methods/applepay.svg',
        'klarna' => 'payment-methods/klarna.svg',
        'ideal' => 'payment-methods/ideal.svg',
        'bancontact' => 'payment-methods/bancontact.svg',
        'invoice' => 'payment-methods/invoice.svg',
        'prepayment' => 'payment-methods/prepayment.svg',
    ];

    /**
     * @return array{code: string, label: string, icon: string|null}
     */
    public function resolve(string $code, string $label): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'icon' => self::ICONS[$code] ?? null,
        ];
    }
}
