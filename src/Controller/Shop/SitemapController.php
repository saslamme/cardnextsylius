<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Seo\ChannelSitemapUrlProvider;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SitemapController
{
    public function __construct(private ChannelContextInterface $channelContext, private ChannelSitemapUrlProvider $urls)
    {
    }

    #[Route('/sitemap.xml', name: 'cardnext_shop_sitemap', methods: ['GET'], priority: 300)]
    public function __invoke(): Response
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (\Throwable) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        if (!$channel instanceof ChannelInterface) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($this->urls->urls($channel) as $url) {
            $xml .= '<url><loc>' . htmlspecialchars($url, \ENT_XML1 | \ENT_QUOTES, 'UTF-8') . '</loc></url>';
        }
        $xml .= '</urlset>';

        return new Response($xml, headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
