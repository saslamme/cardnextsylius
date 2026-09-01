<?php

declare(strict_types=1);

namespace App\Content;

final readonly class ResolvedHomepageContent
{
    public function __construct(
        public string $metaTitle,
        public string $metaDescription,
        public string $heroKicker,
        public string $heroTitle,
        public string $heroText,
        public string $introKicker,
        public string $introTitle,
        public string $introText,
        public string $whyKicker,
        public string $whyTitle,
        public string $whyText,
        public string $ctaKicker,
        public string $ctaTitle,
        public string $ctaText,
        public string $footerText,
        public string $heroImagePath,
        public string $introImagePath,
        public string $ctaImagePath,
    ) {
    }
}
