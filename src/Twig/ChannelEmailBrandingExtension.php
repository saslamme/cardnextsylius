<?php

declare(strict_types=1);

namespace App\Twig;

use App\Email\ChannelEmailBrandingResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ChannelEmailBrandingExtension extends AbstractExtension
{
    public function __construct(private readonly ChannelEmailBrandingResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_channel_email_branding', $this->resolver->resolve(...))];
    }
}
