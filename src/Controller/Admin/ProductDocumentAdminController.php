<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product\Product;
use App\Entity\Product\ProductDocument;
use App\Form\Type\ProductDocumentType;
use App\Service\CardnextMediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class ProductDocumentAdminController extends AbstractController
{
    #[Route('/admin/cardnext/products/{id}/documents', name: 'cardnext_admin_product_document_index', methods: ['GET'])]
    public function index(Product $product): Response
    {
        return $this->render('admin/cardnext/product_document/index.html.twig', [
            'product' => $product,
            'documents' => $product->getDocuments(),
        ]);
    }

    #[Route('/admin/cardnext/products/{id}/documents/new', name: 'cardnext_admin_product_document_create', methods: ['GET', 'POST'])]
    public function create(
        Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
        CardnextMediaStorage $storage,
    ): Response {
        $document = new ProductDocument();
        $document->setProduct($product);

        $form = $this->createForm(ProductDocumentType::class, $document, ['require_file' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('file')->getData();
            if ($file !== null) {
                $storage->uploadProductDocument($document, $file);
            }

            $product->addDocument($document);
            $entityManager->persist($document);
            $entityManager->flush();

            $this->addFlash('success', 'Dokument wurde angelegt.');

            return $this->redirectToRoute('cardnext_admin_product_document_index', ['id' => $product->getId()]);
        }

        return $this->render('admin/cardnext/product_document/form.html.twig', [
            'form' => $form,
            'product' => $product,
            'document' => $document,
            'page_title' => 'Dokument anlegen',
        ]);
    }

    #[Route('/admin/cardnext/product-documents/{id}/edit', name: 'cardnext_admin_product_document_update', methods: ['GET', 'POST'])]
    public function update(
        ProductDocument $document,
        Request $request,
        EntityManagerInterface $entityManager,
        CardnextMediaStorage $storage,
    ): Response {
        $form = $this->createForm(ProductDocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('file')->getData();
            if ($file !== null) {
                $storage->uploadProductDocument($document, $file);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Dokument wurde aktualisiert.');

            return $this->redirectToRoute('cardnext_admin_product_document_index', [
                'id' => $document->getProduct()->getId(),
            ]);
        }

        return $this->render('admin/cardnext/product_document/form.html.twig', [
            'form' => $form,
            'product' => $document->getProduct(),
            'document' => $document,
            'page_title' => 'Dokument bearbeiten',
        ]);
    }

    #[Route('/admin/cardnext/product-documents/{id}/delete', name: 'cardnext_admin_product_document_delete', methods: ['POST'])]
    public function delete(
        ProductDocument $document,
        Request $request,
        EntityManagerInterface $entityManager,
        CardnextMediaStorage $storage,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-product-document-' . $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $productId = $document->getProduct()->getId();

        $storage->removeProductDocument($document);
        $entityManager->remove($document);
        $entityManager->flush();

        $this->addFlash('success', 'Dokument wurde gelöscht.');

        return $this->redirectToRoute('cardnext_admin_product_document_index', ['id' => $productId]);
    }
}
