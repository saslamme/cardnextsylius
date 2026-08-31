<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\International\MarketUrlResolver;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SitemapController
{
    public function __construct(private MarketUrlResolver $markets)
    {
    }

    #[Route('/sitemap.xml', name: 'cardnext_shop_sitemap', methods: ['GET'], priority: 300)]
    public function __invoke(): Response
    {
        $market = $this->markets->currentMarket();
        if ($market === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $url = htmlspecialchars(sprintf('%s/%s/', $market->baseUrl(), $market->localeCode), \ENT_XML1);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . '<url><loc>' . $url . '</loc></url></urlset>';

        return new Response($xml, headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
