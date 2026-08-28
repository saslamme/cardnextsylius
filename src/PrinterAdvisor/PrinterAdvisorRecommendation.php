<?php

declare(strict_types=1);

namespace App\PrinterAdvisor;

use App\Entity\Product\Product;

final readonly class PrinterAdvisorRecommendation
{
    /** @param list<string> $reasons */
    public function __construct(
        public Product $product,
        public int $price,
        public int $score,
        public array $reasons,
        public string $label,
        public string $url = '',
    ) {
    }
}
