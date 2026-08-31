<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Channel\Channel;
use App\Service\ProductComparisonService;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProductComparisonController extends AbstractController
{
    public function __construct(private ProductComparisonService $comparison, private ChannelContextInterface $channelContext)
    {
    }

    public function __invoke(Request $request): Response
    {
        $raw = array_filter(explode(',', (string) $request->query->get('products', '')));
        $limited = array_slice($raw, 0, ProductComparisonService::MAX_PRODUCTS);
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof Channel) {
            throw new \LogicException('Cardnext channel expected.');
        }
        $products = $this->comparison->findComparableProducts($limited, $channel);
        // @phpstan-ignore argument.type
        $comparison = $this->comparison->build($products, $channel, $request->getLocale());

        return $this->render('shop/product_compare/index.html.twig', ['comparison' => $comparison, 'channel' => $channel, 'requestedCount' => count($raw), 'codes' => array_map(static fn ($product): string => (string) $product->getCode(), $products)]);
    }
}
