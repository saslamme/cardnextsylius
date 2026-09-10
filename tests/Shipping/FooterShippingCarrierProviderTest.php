<?php

declare(strict_types=1);

namespace App\Tests\Shipping;

use App\Shipping\FooterShippingCarrierProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FooterShippingCarrierProviderTest extends TestCase
{
    #[Test]
    public function it_always_provides_dhl_and_ups_in_a_stable_order(): void
    {
        self::assertSame([
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
        ], (new FooterShippingCarrierProvider())->provide());
    }
}
