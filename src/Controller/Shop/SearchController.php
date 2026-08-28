<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Product\Product;
use App\Service\Search\CardnextProductSearch;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    private const int RESULTS_PER_PAGE = 24;

    #[Route('/{_locale}/suche', name: 'cardnext_shop_search', methods: ['GET'], priority: 120)]
    public function index(
        string $_locale,
        Request $request,
        CardnextProductSearch $search,
        EntityManagerInterface $entityManager,
    ): Response {
        $query = trim((string) $request->query->get('q', ''));

        if ($query !== '') {
            $exactProduct = $search->findExactProductByIdentifier($query, $_locale);

            if ($exactProduct !== null) {
                return $this->redirectToRoute('sylius_shop_product_show', [
                    '_locale' => $_locale,
                    'slug' => $exactProduct['slug'],
                ]);
            }
        }

        $page = max(1, $request->query->getInt('page', 1));
        $result = $search->search(
            $query,
            $_locale,
            self::RESULTS_PER_PAGE,
            ($page - 1) * self::RESULTS_PER_PAGE,
        );
        $pageCount = max(1, (int) ceil($result['total'] / self::RESULTS_PER_PAGE));

        if ($page > $pageCount) {
            $page = $pageCount;
            $result = $search->search(
                $query,
                $_locale,
                self::RESULTS_PER_PAGE,
                ($page - 1) * self::RESULTS_PER_PAGE,
            );
        }

        return $this->render('shop/search/index.html.twig', [
            'query' => $result['query'],
            'correctedQuery' => $result['correctedQuery'] ?? null,
            'total' => $result['total'],
            'page' => $page,
            'pageCount' => $pageCount,
            'perPage' => self::RESULTS_PER_PAGE,
            'products' => $this->loadProductsInSearchOrder(
                $result['products'],
                $entityManager,
            ),
        ]);
    }

    #[Route('/{_locale}/search/suggest', name: 'cardnext_shop_search_suggest', methods: ['GET'], priority: 120)]
    public function suggest(
        string $_locale,
        Request $request,
        CardnextProductSearch $search,
        EntityManagerInterface $entityManager,
    ): Response {
        $query = trim((string) $request->query->get('q', ''));

        if (mb_strlen($query) < 2) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $result = $search->search($query, $_locale, 7);
        $manufacturerQuery = $result['correctedQuery'] ?? $query;

        return $this->render('shop/search/suggest.html.twig', [
            'query' => $result['query'],
            'correctedQuery' => $result['correctedQuery'] ?? null,
            'total' => $result['total'],
            'products' => $this->loadProductsInSearchOrder(
                $result['products'],
                $entityManager,
            ),
            'manufacturers' => $search->manufacturers($manufacturerQuery, 4),
        ]);
    }

    private function loadProductsInSearchOrder(
        array $searchRows,
        EntityManagerInterface $entityManager,
    ): array {
        if ($searchRows === []) {
            return [];
        }

        $ids = array_map(
            static fn (array $item): int => (int) $item['id'],
            $searchRows,
        );

        $entities = $entityManager
            ->getRepository(Product::class)
            ->findBy(['id' => $ids]);

        $indexed = [];

        foreach ($entities as $product) {
            $id = $product->getId();

            if ($id !== null) {
                $indexed[$id] = $product;
            }
        }

        $products = [];

        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $products[] = $indexed[$id];
            }
        }

        return $products;
    }
}
