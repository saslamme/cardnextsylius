<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Content\ChannelHomepageImageUploader;
use App\Content\ChannelHomepageImageUploadException;
use App\Entity\Content\ChannelHomepageContent;
use App\Form\Type\ChannelHomepageContentType;
use App\Repository\Content\ChannelHomepageContentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class ChannelHomepageContentAdminController extends AbstractController
{
    public function __construct(private readonly ChannelHomepageContentRepository $repository, private readonly EntityManagerInterface $entityManager, private readonly ChannelHomepageImageUploader $imageUploader)
    {
    }

    #[Route('/admin/cardnext/homepage-content', name: 'cardnext_admin_homepage_content_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/cardnext/homepage_content/index.html.twig', ['contents' => $this->repository->findBy([], ['updatedAt' => 'DESC'])]);
    }

    #[Route('/admin/cardnext/homepage-content/new', name: 'cardnext_admin_homepage_content_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->form(new ChannelHomepageContent(), $request, true);
    }

    #[Route('/admin/cardnext/homepage-content/{id}/edit', name: 'cardnext_admin_homepage_content_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $content = $this->repository->find($id);
        if (!$content instanceof ChannelHomepageContent) {
            throw $this->createNotFoundException('Homepage-Inhalte wurden nicht gefunden.');
        }

        return $this->form($content, $request, false);
    }

    private function form(ChannelHomepageContent $content, Request $request, bool $new): Response
    {
        $form = $this->createForm(ChannelHomepageContentType::class, $content);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->imageUploader->upload($content);
            } catch (ChannelHomepageImageUploadException $exception) {
                $form->get($exception->field)->addError(new FormError($exception->getMessage()));

                return $this->render('admin/cardnext/homepage_content/edit.html.twig', ['form' => $form, 'content' => $content, 'page_title' => $new ? 'Homepage-Inhalte anlegen' : 'Homepage-Inhalte bearbeiten']);
            }
            if ($new) {
                $this->entityManager->persist($content);
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'Homepage-Inhalte wurden gespeichert.');

            return $this->redirectToRoute('cardnext_admin_homepage_content_index');
        }

        return $this->render('admin/cardnext/homepage_content/edit.html.twig', ['form' => $form, 'content' => $content, 'page_title' => $new ? 'Homepage-Inhalte anlegen' : 'Homepage-Inhalte bearbeiten']);
    }
}
