<?php

declare(strict_types=1);

namespace App\ProductImage;

final class ProductImageImportResult
{
    /** @var array<string, int> */
    public array $counts = [
        'products_in_manifest' => 0,
        'products_found' => 0,
        'products_missing' => 0,
        'images_requested' => 0,
        'images_valid' => 0,
        'images_missing' => 0,
        'images_invalid' => 0,
        'images_already_assigned' => 0,
        'images_to_create' => 0,
        'products_to_replace' => 0,
    ];

    /** @var list<string> */
    public array $warnings = [];
}
