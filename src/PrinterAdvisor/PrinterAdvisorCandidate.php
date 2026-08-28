<?php

declare(strict_types=1);

namespace App\PrinterAdvisor;

use App\Entity\Product\PrinterAdvisorProfile;
use App\Entity\Product\Product;

final readonly class PrinterAdvisorCandidate
{
    public function __construct(public Product $product, public PrinterAdvisorProfile $profile, public int $price)
    {
    }
}
