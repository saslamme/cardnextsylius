<?php

declare(strict_types=1);

namespace App\Shipping;

final class FooterShippingCarrierProvider
{
    /** @return list<array{code: string, label: string, icon: string}> */
    public function provide(): array
    {
        return [
            [
                'code' => 'dhl',
                'label' => 'DHL',
                'icon' => 'shipping-methods/dhl.svg',
            ],
            [
                'code' => 'ups',
                'label' => 'UPS',
                'icon' => 'shipping-methods/ups.svg',
            ],
        ];
    }
}
