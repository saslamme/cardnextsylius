<?php

declare(strict_types=1);

namespace App\Twig;

use App\Social\FooterSocialLinkProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FooterSocialLinkExtension extends AbstractExtension
{
    public function __construct(private readonly FooterSocialLinkProvider $provider)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_footer_social_links', $this->provider->provide(...))];
    }
}
