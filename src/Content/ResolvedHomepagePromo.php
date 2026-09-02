<?php

declare(strict_types=1);

namespace App\Content;

final readonly class ResolvedHomepagePromo
{
    public function __construct(
        public bool $enabled,
        public string $eyebrow,
        public string $headline,
        public string $text,
        public string $buttonLabel,
        public string $url,
        public ?string $imagePath,
        public string $imageAlt,
        public string $badge,
    ) {
    }
}
