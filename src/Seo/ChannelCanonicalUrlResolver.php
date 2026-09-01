<?php

declare(strict_types=1);

namespace App\Seo;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class ChannelCanonicalUrlResolver
{
    public function __construct(private ChannelContextInterface $channelContext)
    {
    }

    public function resolve(Request $request): ?string
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (\Throwable) {
            return null;
        }

        if (!$channel instanceof ChannelInterface || !$channel->isEnabled()) {
            return null;
        }
        $hostname = trim((string) $channel->getHostname());
        if ($hostname === '') {
            return null;
        }

        // A canonical deliberately describes the route path, not the inbound
        // host or request query (filters, pagination and tracking are variants).
        return 'https://' . $hostname . $request->getBaseUrl() . $request->getPathInfo();
    }

    public function absoluteAsset(Request $request, string $path): ?string
    {
        $canonical = $this->resolve($request);
        if ($canonical === null) {
            return null;
        }

        $origin = parse_url($canonical, \PHP_URL_SCHEME) . '://' . parse_url($canonical, \PHP_URL_HOST);

        return rtrim($origin, '/') . $request->getBaseUrl() . '/' . ltrim($path, '/');
    }
}
