<?php

declare(strict_types=1);

namespace App\Cms;

final readonly class VideoEmbed
{
    public function __construct(
        public string $provider,
        public string $videoId,
        public string $embedUrl,
    ) {
    }
}
