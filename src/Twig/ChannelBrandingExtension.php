<?php
declare(strict_types=1);
namespace App\Twig;
use App\Branding\ChannelBrandingResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
final class ChannelBrandingExtension extends AbstractExtension
{
    public function __construct(private readonly ChannelBrandingResolver $resolver) {}
    public function getFunctions(): array { return [new TwigFunction('cardnext_channel_branding', $this->resolver->resolve(...))]; }
}
