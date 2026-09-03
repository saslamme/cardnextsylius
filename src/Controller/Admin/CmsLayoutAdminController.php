<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Cms\CmsLayout;
use App\Entity\Cms\CmsPage;
use App\Form\Cms\CmsLayoutType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class CmsLayoutAdminController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/admin/cardnext/cms/layouts', name: 'cardnext_admin_cms_layouts', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/cardnext/cms/layout/index.html.twig', ['layouts' => $this->entityManager->getRepository(CmsLayout::class)->findBy([], ['name' => 'ASC'])]);
    }

    #[Route('/admin/cardnext/cms/layouts/new', name: 'cardnext_admin_cms_layout_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->form(new CmsLayout(), $request, true);
    }

    #[Route('/admin/cardnext/cms/layouts/{id}/edit', name: 'cardnext_admin_cms_layout_edit', methods: ['GET', 'POST'])]
    public function edit(CmsLayout $layout, Request $request): Response
    {
        return $this->form($layout, $request, false);
    }

    #[Route('/admin/cardnext/cms/layouts/{id}/delete', name: 'cardnext_admin_cms_layout_delete', methods: ['POST'])]
    public function delete(CmsLayout $layout, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-cms-layout-' . $layout->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $count = $this->entityManager->getRepository(CmsPage::class)->count(['layout' => $layout]);
        if ($count > 0) {
            $this->addFlash('error', sprintf('Dieses Layout wird noch von %d Seiten verwendet.', $count));
        } else {
            $this->entityManager->remove($layout);
            $this->entityManager->flush();
            $this->addFlash('success', 'Layout wurde gelöscht.');
        }

        return $this->redirectToRoute('cardnext_admin_cms_layouts');
    }

    private function form(CmsLayout $layout, Request $request, bool $new): Response
    {
        $form = $this->createForm(CmsLayoutType::class, $layout)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($new) {
                $this->entityManager->persist($layout);
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'Layout wurde gespeichert.');

            return $this->redirectToRoute('cardnext_admin_cms_layouts');
        }

        return $this->render('admin/cardnext/cms/layout/form.html.twig', ['form' => $form, 'layout' => $layout, 'new' => $new]);
    }
}
