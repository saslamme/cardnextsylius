<?php
declare(strict_types=1);
namespace App\Branding;

use App\Entity\Channel\Channel;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final readonly class ChannelBrandingResolver
{
    public function __construct(private ChannelContextInterface $channelContext) {}

    public function resolve(): ChannelBranding
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof Channel) {
            return new ChannelBranding('cardnext', 'Cardnext', 'cardnext/cardnext.svg', 'cardnext/cardnext.svg', null, []);
        }
        $variables = [];
        foreach ([
            '--cn-primary' => $channel->getPrimaryColor(), '--cn-primary-hover' => $channel->getPrimaryHoverColor(),
            '--cn-primary-soft' => $channel->getPrimarySoftColor(), '--cn-ink' => $channel->getInkColor(),
            '--cn-text' => $channel->getTextColor(), '--cn-footer' => $channel->getFooterColor(),
        ] as $name => $value) {
            if ($value !== null && preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/D', $value)) $variables[$name] = $value;
        }
        $logo = $channel->getLogoPath() ?? 'cardnext/cardnext.svg';
        return new ChannelBranding($channel->getThemeKey() ?? 'cardnext', $channel->getBrandName() ?? 'Cardnext', $logo, $channel->getLogoDarkPath() ?? $logo, $channel->getFaviconPath(), $variables);
    }
}
