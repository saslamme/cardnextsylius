<?php

declare(strict_types=1);

namespace App\Pricing;

final readonly class ResolvedVariantPrice
{
    public const CUSTOMER = 'customer';
    public const CUSTOMER_GROUP = 'customer_group';
    public const PUBLIC_TIER = 'public_tier';
    public const CHANNEL_PRICING = 'channel_pricing';

    public function __construct(public int $price, public string $source) {}
}
