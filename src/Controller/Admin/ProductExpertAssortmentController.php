<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product\Product;
use App\Entity\Sales\ProductExpert;
use App\Entity\Sales\ProductExpertProduct;
use App\Entity\User\AdminUser;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/vertrieb')]
final class ProductExpertAssortmentController extends AbstractController
{
    #[Route('/sortiment/{id?}', name: 'cardnext_admin_product_expert_assortment', methods: ['GET'])]
    public function assortment(?ProductExpert $expert, EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/product_expert/assortment.html.twig', [
            'expert' => $this->resolve($expert, $entityManager),
        ]);
    }

    #[Route('/products/search', name: 'cardnext_admin_product_expert_product_search', methods: ['GET'])]
    public function search(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $requestedExpertId = $request->query->getInt('expert');
        $expert = $this->resolve(
            $requestedExpertId > 0 ? $entityManager->find(ProductExpert::class, $requestedExpertId) : null,
            $entityManager,
        );
        $query = mb_strtolower(trim($request->query->getString('q')));

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        /** @var list<Product> $products */
        $products = $entityManager->createQueryBuilder()
            ->select('DISTINCT product')
            ->from(Product::class, 'product')
            ->leftJoin('product.translations', 'translation')
            ->leftJoin('product.variants', 'variant')
            ->leftJoin('product.manufacturer', 'manufacturer')
            ->join('product.channels', 'channel')
            ->andWhere('product.enabled = :enabled')
            ->andWhere('channel = :channel')
            ->andWhere('LOWER(translation.name) LIKE :query OR LOWER(product.code) LIKE :query OR LOWER(variant.code) LIKE :query OR LOWER(manufacturer.name) LIKE :query')
            ->setParameters([
                'enabled' => true,
                'channel' => $expert->getChannel(),
                'query' => '%' . $query . '%',
            ])
            ->orderBy('product.code', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $selectedProductIds = [];
        foreach ($expert->getProducts() as $item) {
            $productId = $item->getProduct()?->getId();
            if ($productId !== null) {
                $selectedProductIds[$productId] = true;
            }
        }

        return $this->json(array_map(
            fn (Product $product): array => $this->productData($product, isset($selectedProductIds[$product->getId()])),
            $products,
        ));
    }

    #[Route('/sortiment/{id}/add', name: 'cardnext_admin_product_expert_assortment_add', methods: ['POST'])]
    public function add(ProductExpert $expert, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $expert = $this->resolve($expert, $entityManager);
        $this->validateCsrfToken($request);
        $product = $entityManager->find(Product::class, $request->request->getInt('product'));

        if (!$product instanceof Product || !$product->isEnabled() || !$product->hasChannel($expert->getChannel())) {
            throw $this->createNotFoundException();
        }

        if ($entityManager->getRepository(ProductExpertProduct::class)->findOneBy(['expert' => $expert, 'product' => $product]) !== null) {
            return $this->json(['added' => false], Response::HTTP_CONFLICT);
        }

        $lastPosition = $entityManager->createQueryBuilder()
            ->select('MAX(item.position)')
            ->from(ProductExpertProduct::class, 'item')
            ->andWhere('item.expert = :expert')
            ->setParameter('expert', $expert)
            ->getQuery()
            ->getSingleScalarResult();

        $item = new ProductExpertProduct();
        $item->setExpert($expert);
        $item->setProduct($product);
        $item->setPosition($lastPosition === null ? 0 : ((int) $lastPosition) + 1);
        $entityManager->persist($item);

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(['added' => false], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'added' => true,
            'itemId' => $item->getId(),
            'product' => $this->productData($product, true),
        ]);
    }

    #[Route('/sortiment/{id}/remove/{item}', name: 'cardnext_admin_product_expert_assortment_remove', methods: ['POST'])]
    public function remove(ProductExpert $expert, ProductExpertProduct $item, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $expert = $this->resolve($expert, $entityManager);
        $this->validateCsrfToken($request);

        if ($item->getExpert() !== $expert) {
            throw $this->createAccessDeniedException();
        }

        $productId = $item->getProduct()?->getId();
        $entityManager->remove($item);
        $entityManager->flush();

        return $this->json(['removed' => true, 'productId' => $productId]);
    }

    #[Route('/sortiment/{id}/sort', name: 'cardnext_admin_product_expert_assortment_sort', methods: ['POST'])]
    public function sort(ProductExpert $expert, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $expert = $this->resolve($expert, $entityManager);
        $this->validateCsrfToken($request);
        $submittedIds = $request->toArray()['items'] ?? null;

        if (!is_array($submittedIds) || !array_is_list($submittedIds)) {
            return $this->json(['saved' => false], Response::HTTP_BAD_REQUEST);
        }

        $ids = array_map(
            static fn (mixed $id): int => is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : 0,
            $submittedIds,
        );
        if (in_array(0, $ids, true) || count($ids) !== count(array_unique($ids))) {
            return $this->json(['saved' => false], Response::HTTP_BAD_REQUEST);
        }

        $itemsById = [];
        foreach ($expert->getProducts() as $item) {
            if ($item->getId() !== null) {
                $itemsById[$item->getId()] = $item;
            }
        }

        if (count($ids) !== count($itemsById)) {
            throw $this->createAccessDeniedException();
        }

        foreach ($ids as $id) {
            if (!isset($itemsById[$id]) || $itemsById[$id]->getExpert() !== $expert) {
                throw $this->createAccessDeniedException();
            }
        }

        foreach ($ids as $position => $id) {
            $itemsById[$id]->setPosition($position);
        }

        $entityManager->flush();

        return $this->json(['saved' => true]);
    }

    private function resolve(?ProductExpert $requested, EntityManagerInterface $entityManager): ProductExpert
    {
        if ($this->isGranted('ROLE_ADMINISTRATION_ACCESS')) {
            if ($requested !== null) {
                return $requested;
            }

            throw $this->createNotFoundException('Bitte einen Experten auswählen.');
        }

        $this->denyAccessUnlessGranted('ROLE_PRODUCT_EXPERT');
        $user = $this->getUser();
        if (!$user instanceof AdminUser) {
            throw $this->createAccessDeniedException();
        }

        $ownExpert = $entityManager->getRepository(ProductExpert::class)->findOneBy(['adminUser' => $user]);
        if (!$ownExpert instanceof ProductExpert) {
            throw $this->createAccessDeniedException('Kein Produktexperten-Profil zugeordnet.');
        }
        if ($requested !== null && $requested !== $ownExpert) {
            throw $this->createAccessDeniedException();
        }

        return $ownExpert;
    }

    /** @return array{id: int|null, name: string|null, code: string|null, manufacturer: string|null, image: string|null, alreadySelected: bool} */
    private function productData(Product $product, bool $alreadySelected): array
    {
        $image = $product->getImages()->first();

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'code' => $product->getCode(),
            'manufacturer' => $product->getManufacturer()?->getName(),
            'image' => $image === false || $image->getPath() === null ? null : $this->generateUrl('liip_imagine_filter', [
                'filter' => 'sylius_shop_product_small_thumbnail',
                'path' => ltrim($image->getPath(), '/'),
            ]),
            'alreadySelected' => $alreadySelected,
        ];
    }

    private function validateCsrfToken(Request $request): void
    {
        $token = $request->headers->get('X-CSRF-TOKEN') ?? $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('product_expert_assortment', $token)) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
    }
}
