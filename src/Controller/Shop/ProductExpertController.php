<?php
declare(strict_types=1);
namespace App\Controller\Shop;
use App\Entity\Sales\ProductExpert;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class ProductExpertController extends AbstractController
{
    #[Route('/experte/{slug}', name: 'cardnext_shop_product_expert_show', methods: ['GET'])]
    public function __invoke(string $slug, ChannelContextInterface $channels, EntityManagerInterface $em): Response
    {
        $expert = $em->getRepository(ProductExpert::class)->findOneBy(['channel' => $channels->getChannel(), 'slug' => $slug, 'enabled' => true]);
        if (!$expert) throw $this->createNotFoundException();
        $items = $expert->getProducts()->toArray();
        usort($items, static fn ($a, $b): int => [$a->getPosition(), $a->getProduct()?->getId() ?? 0] <=> [$b->getPosition(), $b->getProduct()?->getId() ?? 0]);
        $products = []; foreach ($items as $item) { $product = $item->getProduct(); if ($product?->isEnabled() && $product->hasChannel($channels->getChannel())) $products[] = $product; }
        return $this->render('shop/product_expert/show.html.twig', ['expert' => $expert, 'products' => $products]);
    }
}
