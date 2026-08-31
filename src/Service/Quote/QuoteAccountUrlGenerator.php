<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Quote\Quote;
use App\International\CardnextMarketRegistry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class QuoteAccountUrlGenerator
{
    /** @param list<string> $allowedChannels */
    public function __construct(private UrlGeneratorInterface $router, private CardnextMarketRegistry $markets, private array $allowedChannels)
    {
    }

    public function view(Quote $quote): string
    {
        $market = $this->markets->get($quote->getChannelCode());
        if ($market === null || !in_array($quote->getChannelCode(), $this->allowedChannels, true)) {
            throw new \DomainException(sprintf('No quote account base URL is configured for channel "%s".', $quote->getChannelCode()));
        }
        $path = $this->router->generate('cardnext_shop_account_quote_show', [
            '_locale' => $quote->getLocaleCode(), 'number' => $quote->getNumber(), 'version' => $quote->getVersion(),
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        return $market->baseUrl() . '/' . ltrim($path, '/');
    }
}
