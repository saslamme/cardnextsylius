<?php
declare(strict_types=1);
namespace App\Branding;

final readonly class ChannelBranding
{
    /** @param array<string, string> $cssVariables */
    public function __construct(
        public string $themeKey,
        public string $brandName,
        public string $logoPath,
        public string $logoDarkPath,
        public ?string $faviconPath,
        public array $cssVariables,
    ) {}
}
