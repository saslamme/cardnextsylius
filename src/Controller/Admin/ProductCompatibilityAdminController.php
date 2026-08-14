<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product\Product;
use App\Entity\Product\ProductCompatibility;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class ProductCompatibilityAdminController extends AbstractController
{
    #[Route('/admin/cardnext/products/{id}/compatibilities', name: 'cardnext_admin_product_compatibility_index', methods: ['GET'])]
    public function index(Product $product): Response
    {
        return $this->render('admin/cardnext/product_compatibility/index.html.twig', [
            'product' => $product,
            'outgoing' => $product->getCompatibilities(),
            'incoming' => $product->getReverseCompatibilities(),
            'type_labels' => ProductCompatibility::typeLabels(),
        ]);
    }

    #[Route('/admin/cardnext/products/{id}/compatibilities', name: 'cardnext_admin_product_compatibility_create', methods: ['POST'])]
    public function create(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'create-product-compatibility-' . $product->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $targetCode = trim((string) $request->request->get('target_code'));
        $relationType = trim((string) $request->request->get('relation_type'));
        $direction = (string) $request->request->get('direction', 'outgoing');

        if ($targetCode === '') {
            $this->addFlash('error', 'Bitte einen Produktcode angeben.');

            return $this->redirectToRoute('cardnext_admin_product_compatibility_index', ['id' => $product->getId()]);
        }

        /** @var Product|null $otherProduct */
        $otherProduct = $entityManager->getRepository(Product::class)->findOneBy(['code' => $targetCode]);
        if (!$otherProduct instanceof Product) {
            $this->addFlash('error', sprintf('Produkt "%s" wurde nicht gefunden.', $targetCode));

            return $this->redirectToRoute('cardnext_admin_product_compatibility_index', ['id' => $product->getId()]);
        }

        if ($otherProduct === $product) {
            $this->addFlash('error', 'Ein Produkt kann nicht mit sich selbst verknüpft werden.');

            return $this->redirectToRoute('cardnext_admin_product_compatibility_index', ['id' => $product->getId()]);
        }

        if (!array_key_exists($relationType, ProductCompatibility::typeLabels())) {
            $this->addFlash('error', 'Ungültiger Verknüpfungstyp.');

            return $this->redirectToRoute('cardnext_admin_product_compatibility_index', ['id' => $product->getId()]);
        }

        $source = $direction === 'incoming' ? $otherProduct : $product;
        $target = $direction === 'incoming' ? $product : $otherProduct;

        $existing = $entityManager->getRepository(ProductCompatibility::class)->findOneBy([
            'sourceProduct' => $source,
            'targetProduct' => $target,
            'relationType' => $relationType,
        ]);

        if ($existing instanceof ProductCompatibility) {
            $this->addFlash('error', 'Diese Produktverknüpfung existiert bereits.');

            return $this->redirectToRoute('cardnext_admin_product_compatibility_index', ['id' => $product->getId()]);
        }

        $compatibility = new ProductCompatibility();
        $compatibility->setSourceProduct($source);
        $compatibility->setTargetProduct($target);
        $compatibility->setRelationType($relationType);
        $compatibility->setNote((string) $request->request->get('note'));
        $compatibility->setPosition((int) $request->request->get('position', 0));
        $compatibility->setEnabled($request->request->has('enabled'));

        $source->addCompatibility($compatibility);
        $target->addReverseCompatibility($compatibility);

        $entityManager->persist($compatibility);
        $entityManager->flush();

        $this->addFlash('success', 'Produktverknüpfung wurde angelegt.');

        return $this->redirectToRoute('cardnext_admin_product_compatibility_index', ['id' => $product->getId()]);
    }

    #[Route('/admin/cardnext/product-compatibilities/{id}/delete', name: 'cardnext_admin_product_compatibility_delete', methods: ['POST'])]
    public function delete(
        ProductCompatibility $compatibility,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'delete-product-compatibility-' . $compatibility->getId(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $returnProductId = (int) $request->request->get('return_product_id');
        $source = $compatibility->getSourceProduct();
        $target = $compatibility->getTargetProduct();

        if ($returnProductId !== $source->getId() && $returnProductId !== $target->getId()) {
            throw $this->createAccessDeniedException('Ungültiges Rücksprungprodukt.');
        }

        $entityManager->remove($compatibility);
        $entityManager->flush();

        $this->addFlash('success', 'Produktverknüpfung wurde gelöscht.');

        return $this->redirectToRoute('cardnext_admin_product_compatibility_index', ['id' => $returnProductId]);
    }
}
