<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Content\LegalPage;
use App\Form\Type\LegalPageType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class LegalPageAdminController extends AbstractController
{
    #[Route('/admin/cardnext/legal-pages', name: 'cardnext_admin_legal_page_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $pages = $entityManager
            ->getRepository(LegalPage::class)
            ->findBy([], ['localeCode' => 'ASC', 'code' => 'ASC']);

        return $this->render('admin/cardnext/legal/index.html.twig', [
            'pages' => $pages,
            'page_title' => 'Rechtstexte',
        ]);
    }

    #[Route('/admin/cardnext/legal-pages/new', name: 'cardnext_admin_legal_page_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $page = new LegalPage();
        $form = $this->createForm(LegalPageType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($page);
            $entityManager->flush();
            $this->addFlash('success', sprintf('%s wurde angelegt.', $page->getTitle()));

            return $this->redirectToRoute('cardnext_admin_legal_page_index');
        }

        return $this->render('admin/cardnext/legal/edit.html.twig', [
            'form' => $form,
            'page' => $page,
            'page_title' => 'Rechtstext anlegen',
        ]);
    }

    #[Route('/admin/cardnext/legal-pages/{id}/edit', name: 'cardnext_admin_legal_page_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $page = $entityManager->getRepository(LegalPage::class)->find($id);

        if (!$page instanceof LegalPage) {
            throw $this->createNotFoundException('Rechtstext wurde nicht gefunden.');
        }

        $form = $this->createForm(LegalPageType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', sprintf('%s wurde gespeichert.', $page->getTitle()));

            return $this->redirectToRoute('cardnext_admin_legal_page_index');
        }

        return $this->render('admin/cardnext/legal/edit.html.twig', [
            'form' => $form,
            'page' => $page,
            'page_title' => $page->getTitle() . ' bearbeiten',
        ]);
    }
}
