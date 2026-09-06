<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product\Product;
use App\Entity\Product\ProductBundle;
use App\Form\Type\ProductBundleType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class ProductBundleAdminController extends AbstractController
{
    #[Route('/admin/cardnext/products/{id}/bundles', name: 'cardnext_admin_product_bundle_index', methods: ['GET'])]
    public function index(Product $product): Response
    {
        return $this->render('admin/cardnext/product_bundle/index.html.twig', [
            'product' => $product,
            'bundles' => $product->getBundles(),
        ]);
    }

    #[Route('/admin/cardnext/products/{id}/bundles/new', name: 'cardnext_admin_product_bundle_create', methods: ['GET', 'POST'])]
    public function create(Product $product, Request $request, EntityManagerInterface $entityManager): Response
    {
        $bundle = new ProductBundle();
        $product->addBundle($bundle);

        $form = $this->createForm(ProductBundleType::class, $bundle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($bundle);
            $entityManager->flush();
            $this->addFlash('success', 'Bundle wurde angelegt.');

            return $this->redirectToRoute('cardnext_admin_product_bundle_index', ['id' => $product->getId()]);
        }

        return $this->renderBundleForm($form->createView(), $bundle, $product, 'Bundle anlegen');
    }

    #[Route('/admin/cardnext/product-bundles/{id}/edit', name: 'cardnext_admin_product_bundle_update', methods: ['GET', 'POST'])]
    public function update(ProductBundle $bundle, Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = $bundle->getMainProduct();
        $form = $this->createForm(ProductBundleType::class, $bundle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Bundle wurde aktualisiert.');

            return $this->redirectToRoute('cardnext_admin_product_bundle_index', ['id' => $product->getId()]);
        }

        return $this->renderBundleForm($form->createView(), $bundle, $product, 'Bundle bearbeiten');
    }

    #[Route('/admin/cardnext/product-bundles/{id}/delete', name: 'cardnext_admin_product_bundle_delete', methods: ['POST'])]
    public function delete(ProductBundle $bundle, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-product-bundle-' . $bundle->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $product = $bundle->getMainProduct();
        $product->removeBundle($bundle);
        $entityManager->remove($bundle);
        $entityManager->flush();
        $this->addFlash('success', 'Bundle wurde gelöscht.');

        return $this->redirectToRoute('cardnext_admin_product_bundle_index', ['id' => $product->getId()]);
    }

    private function renderBundleForm(FormView $form, ProductBundle $bundle, Product $product, string $pageTitle): Response
    {
        return $this->render('admin/cardnext/product_bundle/form.html.twig', [
            'form' => $form,
            'bundle' => $bundle,
            'product' => $product,
            'page_title' => $pageTitle,
        ]);
    }
}
