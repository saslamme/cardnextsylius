<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Quote\Quote;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class QuotePublicUrlGenerator
{
    /** @param array<string, string> $baseUrls */
    public function __construct(
        private UrlGeneratorInterface $router,
        private array $baseUrls,
    ) {
    }

    public function view(Quote $quote, string $token): string
    {
        return $this->generate('cardnext_shop_quote_public', $quote, $token);
    }

    public function pdf(Quote $quote, string $token): string
    {
        return $this->generate('cardnext_shop_quote_public_pdf', $quote, $token);
    }

    private function generate(string $route, Quote $quote, string $token): string
    {
        $baseUrl = $this->baseUrls[$quote->getChannelCode()] ?? null;
        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new \DomainException(sprintf('No public quote base URL is configured for channel "%s".', $quote->getChannelCode()));
        }

        $path = $this->router->generate($route, [
            '_locale' => $quote->getLocaleCode(),
            'number' => $quote->getNumber(),
            'version' => $quote->getVersion(),
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
