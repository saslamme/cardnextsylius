<?php

declare(strict_types=1);

namespace App\Maintenance;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;

final readonly class MaintenanceOffer
{
    public const CATEGORY_SERVICE = 'service';

    public const CATEGORY_WARRANTY = 'warranty';

    public function __construct(public Product $product, public ProductVariant $variant, public int $price, public string $currencyCode, public string $category)
    {
    }
}
