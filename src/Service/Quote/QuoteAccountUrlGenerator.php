<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Quote\Quote;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class QuoteAccountUrlGenerator
{
    /** @param array<string, string> $baseUrls */
    public function __construct(private UrlGeneratorInterface $router, private array $baseUrls) {}

    public function view(Quote $quote): string
    {
        $baseUrl = $this->baseUrls[$quote->getChannelCode()] ?? null;
        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new \DomainException(sprintf('No quote account base URL is configured for channel "%s".', $quote->getChannelCode()));
        }
        $path = $this->router->generate('cardnext_shop_account_quote_show', [
            '_locale' => $quote->getLocaleCode(), 'number' => $quote->getNumber(), 'version' => $quote->getVersion(),
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
