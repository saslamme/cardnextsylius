<?php

declare(strict_types=1);

namespace App\Payment;

interface AdyenExpressCheckoutAvailabilityInterface
{
    public function isAvailable(): bool;
}
