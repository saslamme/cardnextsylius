<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Quote\QuoteCartService;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class QuoteCartExtension extends AbstractExtension
{
    public function __construct(private QuoteCartService $cart, private ChannelContextInterface $channels)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_quote_cart_count', function (): int {
            $channel = $this->channels->getChannel();

            return $channel instanceof \Sylius\Component\Core\Model\ChannelInterface ? $this->cart->count($channel) : 0;
        })];
    }
}
