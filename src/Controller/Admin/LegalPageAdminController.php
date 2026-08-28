<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Content\LegalPage;
use App\Form\Type\LegalPageType;
use App\Repository\Content\LegalPageRepository;
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
    public function __construct(
        private readonly LegalPageRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/admin/cardnext/legal-pages', name: 'cardnext_admin_legal_page_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/cardnext/legal/index.html.twig', [
            'pages' => $this->repository->findAllWithChannels(),
            'page_title' => 'Rechtstexte',
        ]);
    }

    #[Route('/admin/cardnext/legal-pages/new', name: 'cardnext_admin_legal_page_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $page = new LegalPage();
        $form = $this->createForm(LegalPageType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($page);
            $this->entityManager->flush();
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
    ): Response {
        $page = $this->repository->find($id);

        if (!$page instanceof LegalPage) {
            throw $this->createNotFoundException('Rechtstext wurde nicht gefunden.');
        }

        $form = $this->createForm(LegalPageType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
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
