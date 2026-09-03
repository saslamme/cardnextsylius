<?php

declare(strict_types=1);

namespace App\Maintenance;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;

final readonly class MaintenanceOffer
{
    public function __construct(public Product $product, public ProductVariant $variant, public int $price, public string $currencyCode)
    {
    }
}
