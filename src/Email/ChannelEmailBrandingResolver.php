<?php

declare(strict_types=1);

namespace App\Email;

use App\Branding\ChannelBrandingResolver;
use App\Entity\Channel\Channel;
use Sylius\Component\Channel\Model\ChannelInterface;

final readonly class ChannelEmailBrandingResolver
{
    public function __construct(
        private ChannelBrandingResolver $brandingResolver,
        private string $defaultSenderAddress,
    ) {
    }

    public function resolve(ChannelInterface $channel): ChannelEmailBranding
    {
        $branding = $this->brandingResolver->resolveChannel($channel);
        $senderName = $channel instanceof Channel ? $channel->getEmailSenderName() : null;
        $senderAddress = $channel instanceof Channel ? $channel->getEmailSenderAddress() : null;
        $replyTo = $channel instanceof Channel ? $channel->getEmailReplyToAddress() : null;
        $contactEmail = method_exists($channel, 'getContactEmail') ? $channel->getContactEmail() : null;
        $replyTo ??= is_string($contactEmail) && filter_var($contactEmail, \FILTER_VALIDATE_EMAIL) !== false ? $contactEmail : null;
        $hostname = trim((string) $channel->getHostname());
        $logoPath = ltrim($branding->logoPath, '/');

        return new ChannelEmailBranding(
            $branding->brandName,
            $branding->logoPath,
            $hostname === '' ? '/' . $logoPath : 'https://' . $hostname . '/' . $logoPath,
            $senderName ?? $branding->brandName,
            $senderAddress ?? $this->defaultSenderAddress,
            $replyTo,
        );
    }
}
