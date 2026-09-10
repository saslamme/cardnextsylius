<?php

declare(strict_types=1);

namespace App\Social;

use App\Entity\Channel\Channel;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final class FooterSocialLinkProvider
{
    public function __construct(private readonly ChannelContextInterface $channelContext)
    {
    }

    /** @return list<array{code: string, label: string, url: string, icon: string}> */
    public function provide(): array
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof Channel) {
            return [];
        }

        $links = [];
        foreach ([
            ['instagram', 'Instagram', $channel->getInstagramUrl(), 'social/instagram.svg'],
            ['facebook', 'Facebook', $channel->getFacebookUrl(), 'social/facebook.svg'],
            ['linkedin', 'LinkedIn', $channel->getLinkedinUrl(), 'social/linkedin.svg'],
            ['youtube', 'YouTube', $channel->getYoutubeUrl(), 'social/youtube.svg'],
        ] as [$code, $label, $url, $icon]) {
            if ($url !== null && $url !== '') {
                $links[] = compact('code', 'label', 'url', 'icon');
            }
        }

        return $links;
    }
}
