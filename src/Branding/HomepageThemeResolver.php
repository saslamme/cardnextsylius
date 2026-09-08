<?php

declare(strict_types=1);

namespace App\Branding;

use App\Entity\Channel\Channel;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final readonly class HomepageThemeResolver
{
    public const DEFAULT_THEME = 'cardnext';

    /** @var list<string> */
    public const THEMES = [self::DEFAULT_THEME, 'identible', 'inplastor'];

    public function __construct(private ChannelContextInterface $channelContext)
    {
    }

    public function resolve(): string
    {
        $channel = $this->channelContext->getChannel();

        return self::normalize($channel instanceof Channel ? $channel->getThemeKey() : null);
    }

    public static function normalize(?string $themeKey): string
    {
        $themeKey = strtolower(trim((string) $themeKey));

        return in_array($themeKey, self::THEMES, true) ? $themeKey : self::DEFAULT_THEME;
    }
}
