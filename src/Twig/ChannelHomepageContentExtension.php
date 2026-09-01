<?php

declare(strict_types=1);

namespace App\Twig;

use App\Content\ChannelHomepageContentResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ChannelHomepageContentExtension extends AbstractExtension
{
    public function __construct(private readonly ChannelHomepageContentResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_homepage_content', $this->resolver->resolve(...))];
    }
}
