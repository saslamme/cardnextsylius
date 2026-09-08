<?php

declare(strict_types=1);

namespace App\Twig;

use App\Branding\HomepageThemeResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class HomepageThemeExtension extends AbstractExtension
{
    public function __construct(private readonly HomepageThemeResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_homepage_theme', $this->resolver->resolve(...))];
    }
}
