<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Service\BrandCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BrandController extends AbstractController
{
    #[Route('/marken', name: 'cardnext_shop_brands', methods: ['GET'], priority: 120)]
    public function index(BrandCatalog $catalog): Response
    {
        $manufacturers = $catalog->manufacturers();
        $groups = [];
        $featured = [];
        foreach ($manufacturers as $manufacturer) {
            $letter = mb_strtoupper(mb_substr($manufacturer->getName(), 0, 1));
            $groups[$letter][] = $manufacturer;
            if ($manufacturer->isFeatured()) {
                $featured[] = $manufacturer;
            }
        }
        usort($featured, static fn ($a, $b) => [$a->getFeaturedPosition(), $a->getName()] <=> [$b->getFeaturedPosition(), $b->getName()]);

        return $this->render('shop/brand/index.html.twig', ['groups' => $groups, 'featured' => array_slice($featured, 0, 8)]);
    }

    #[Route('/marken/{slug}', name: 'cardnext_shop_brand_show', methods: ['GET'], priority: 120)]
    public function show(string $slug, Request $request, BrandCatalog $catalog): Response
    {
        $manufacturer = $catalog->manufacturer($slug);
        if ($manufacturer === null) {
            throw $this->createNotFoundException();
        }
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 24;
        $pageCount = max(1, (int) ceil($catalog->productCount($manufacturer) / $perPage));
        if ($page > $pageCount) {
            throw $this->createNotFoundException();
        }

        return $this->render('shop/brand/show.html.twig', [
            'manufacturer' => $manufacturer, 'areas' => $catalog->areas($manufacturer, $request->getLocale()),
            'products' => $catalog->products($manufacturer, $perPage, ($page - 1) * $perPage), 'page' => $page, 'pageCount' => $pageCount,
        ]);
    }
}
