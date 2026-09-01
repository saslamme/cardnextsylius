<?php

declare(strict_types=1);

namespace App\Email;

final readonly class ChannelEmailBranding
{
    public function __construct(
        public string $brandName,
        public string $logoPath,
        public string $logoUrl,
        public string $senderName,
        public string $senderAddress,
        public ?string $replyToAddress,
    ) {
    }
}
