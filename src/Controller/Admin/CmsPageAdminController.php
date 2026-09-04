<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Cms\CmsBlockRendererRegistry;
use App\Cms\CmsImageUploader;
use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsBlock;
use App\Entity\Cms\CmsMenuItem;
use App\Entity\Cms\CmsPage;
use App\Entity\Cms\CmsPageTranslation;
use App\Entity\Cms\CmsRedirect;
use App\Entity\Locale\Locale;
use App\Form\Cms\CmsBlockType;
use App\Form\Cms\CmsPageType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class CmsPageAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CmsBlockRendererRegistry $blockRegistry,
        private readonly CmsImageUploader $imageUploader,
    ) {
    }

    #[Route('/admin/cardnext/cms/pages', name: 'cardnext_admin_cms_pages', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $builder = $this->entityManager->getRepository(CmsPage::class)->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')->addSelect('t')
            ->leftJoin('p.channels', 'c')->addSelect('c')
            ->join('p.layout', 'l')->addSelect('l')->orderBy('p.updatedAt', 'DESC')->distinct();
        if (($search = trim($request->query->getString('q'))) !== '') {
            $builder->andWhere('LOWER(p.code) LIKE :q OR LOWER(t.title) LIKE :q')->setParameter('q', '%' . strtolower($search) . '%');
        }
        foreach (['status' => 'p.status', 'channel' => 'c.code', 'layout' => 'l.code'] as $parameter => $field) {
            if (($value = $request->query->getString($parameter)) !== '') {
                $builder->andWhere($field . ' = :' . $parameter)->setParameter($parameter, $value);
            }
        }

        return $this->render('admin/cardnext/cms/page/index.html.twig', [
            'pages' => $builder->getQuery()->getResult(),
            'layouts' => $this->entityManager->getRepository(\App\Entity\Cms\CmsLayout::class)->findBy([], ['name' => 'ASC']),
            'channels' => $this->entityManager->getRepository(Channel::class)->findBy([], ['code' => 'ASC']),
        ]);
    }

    #[Route('/admin/cardnext/cms/pages/new', name: 'cardnext_admin_cms_page_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $page = new CmsPage();
        $this->addMissingTranslations($page);

        return $this->pageForm($page, $request, true);
    }

    #[Route('/admin/cardnext/cms/pages/{id}/edit', name: 'cardnext_admin_cms_page_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(CmsPage $page, Request $request): Response
    {
        $this->addMissingTranslations($page);

        return $this->pageForm($page, $request, false);
    }

    #[Route('/admin/cardnext/cms/pages/{id}/delete', name: 'cardnext_admin_cms_page_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(CmsPage $page, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-cms-page-' . $page->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $menus = $this->entityManager->getRepository(CmsMenuItem::class)->count(['page' => $page]);
        $redirects = $this->entityManager->getRepository(CmsRedirect::class)->count(['targetPage' => $page]);
        if ($menus + $redirects > 0) {
            $this->addFlash('error', sprintf('Diese Seite wird noch von %d Navigationseinträgen und %d Weiterleitungen verwendet.', $menus, $redirects));
        } else {
            foreach ($page->getBlocks() as $block) {
                foreach ($this->configurationImages($block->getConfiguration()) as $image) {
                    $this->imageUploader->delete($image);
                }
            }
            $this->entityManager->remove($page);
            $this->entityManager->flush();
            $this->addFlash('success', 'Seite wurde gelöscht.');
        }

        return $this->redirectToRoute('cardnext_admin_cms_pages');
    }

    #[Route('/admin/cardnext/cms/pages/{id}/preview', name: 'cardnext_admin_cms_page_preview', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function preview(CmsPage $page, Request $request): Response
    {
        $channel = $this->entityManager->getRepository(Channel::class)->find($request->query->getInt('channel'));
        $locale = $request->query->getString('locale');
        $translation = $page->getTranslation($locale);
        if (!$channel instanceof Channel || !$page->getChannels()->contains($channel) || $translation === null) {
            throw $this->createNotFoundException('Diese Vorschau ist für Kanal und Sprache nicht verfügbar.');
        }

        return $this->render('shop/cms/show.html.twig', ['page' => $page, 'translation' => $translation, 'locale' => $locale, 'preview' => true]);
    }

    #[Route('/admin/cardnext/cms/pages/{page}/blocks/new', name: 'cardnext_admin_cms_block_new', methods: ['GET', 'POST'])]
    public function createBlock(CmsPage $page, Request $request): Response
    {
        $block = new CmsBlock();
        $block->setPosition(($page->getBlocks()->count() + 1) * 10);
        $page->addBlock($block);

        return $this->blockForm($page, $block, $request, true);
    }

    #[Route('/admin/cardnext/cms/pages/{page}/blocks/{id}/edit', name: 'cardnext_admin_cms_block_edit', methods: ['GET', 'POST'])]
    public function editBlock(CmsPage $page, CmsBlock $block, Request $request): Response
    {
        if ($block->getPage() !== $page) {
            throw $this->createNotFoundException();
        }

        return $this->blockForm($page, $block, $request, false);
    }

    #[Route('/admin/cardnext/cms/pages/{page}/blocks/{id}/delete', name: 'cardnext_admin_cms_block_delete', methods: ['POST'])]
    public function deleteBlock(CmsPage $page, CmsBlock $block, Request $request): Response
    {
        if ($block->getPage() !== $page || !$this->isCsrfTokenValid('delete-cms-block-' . $block->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        foreach ($this->configurationImages($block->getConfiguration()) as $image) {
            $this->imageUploader->delete($image);
        }
        $this->entityManager->remove($block);
        $this->entityManager->flush();
        $this->addFlash('success', 'Block wurde gelöscht.');

        return $this->redirectToRoute('cardnext_admin_cms_page_edit', ['id' => $page->getId()]);
    }

    private function pageForm(CmsPage $page, Request $request, bool $new): Response
    {
        $form = $this->createForm(CmsPageType::class, $page)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($new) {
                $this->entityManager->persist($page);
            }
            $this->entityManager->flush();
            $this->addFlash('success', $new ? 'Seite wurde erstellt.' : 'Seite wurde gespeichert.');

            return $this->redirectToRoute('cardnext_admin_cms_page_edit', ['id' => $page->getId()]);
        }

        return $this->render('admin/cardnext/cms/page/form.html.twig', [
            'form' => $form,
            'page' => $page,
            'new' => $new,
            'locale_names' => $this->localeNames(),
        ]);
    }

    private function blockForm(CmsPage $page, CmsBlock $block, Request $request, bool $new): Response
    {
        $form = $this->createForm(CmsBlockType::class, $block, ['locale_choices' => $this->localeChoices()])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $oldConfiguration = $block->getConfiguration();
            $oldImages = $this->configurationImages($oldConfiguration);
            $uploadedImages = [];
            $configuration = [];
            foreach ($form as $name => $field) {
                if (in_array($name, ['locale', 'type', 'position', 'enabled', 'image'], true)) {
                    continue;
                }
                $configuration[$name] = $field->getData();
            }
            $upload = $form->has('image') ? $form->get('image')->getData() : null;
            if ($form->has('image') && isset($oldConfiguration['image'])) {
                $configuration['image'] = $oldConfiguration['image'];
            }
            if ($upload instanceof UploadedFile) {
                try {
                    $configuration['image'] = $this->imageUploader->upload($upload);
                    $uploadedImages[] = $configuration['image'];
                } catch (\InvalidArgumentException|\RuntimeException $exception) {
                    $form->get('image')->addError(new FormError($exception->getMessage()));
                }
            }
            if ($form->has('items') && $block->getType() === 'gallery') {
                $configuration['items'] = [];
                foreach ($form->get('items') as $index => $itemForm) {
                    $itemData = is_array($itemForm->getData()) ? $itemForm->getData() : [];
                    $existing = $itemData['existingImage'] ?? null;
                    $image = is_string($existing) && in_array($existing, $oldImages, true) ? $existing : null;
                    $itemUpload = $itemForm->get('image')->getData();
                    if ($itemUpload instanceof UploadedFile) {
                        try {
                            $image = $this->imageUploader->upload($itemUpload);
                            $uploadedImages[] = $image;
                        } catch (\InvalidArgumentException|\RuntimeException $exception) {
                            $itemForm->get('image')->addError(new FormError($exception->getMessage()));
                        }
                    }
                    $configuration['items'][] = [
                        'image' => $image,
                        'alt' => is_string($itemData['alt'] ?? null) ? trim($itemData['alt']) : '',
                        'caption' => is_string($itemData['caption'] ?? null) ? trim($itemData['caption']) : '',
                    ];
                }
            }
            foreach ($this->blockRegistry->validate($block->getType(), $configuration) as $error) {
                $form->addError(new FormError($error));
            }
            if ($form->isValid()) {
                $block->setConfiguration($configuration);

                try {
                    if ($new) {
                        $this->entityManager->persist($block);
                    }
                    $this->entityManager->flush();
                } catch (\Throwable $exception) {
                    foreach ($uploadedImages as $image) {
                        $this->imageUploader->delete($image);
                    }

                    throw $exception;
                }
                $usedImages = $this->configurationImages($configuration);
                foreach (array_diff($oldImages, $usedImages) as $image) {
                    $this->imageUploader->delete($image);
                }
                $this->addFlash('success', 'Block wurde gespeichert.');

                return $this->redirectToRoute('cardnext_admin_cms_page_edit', ['id' => $page->getId()]);
            }
            foreach ($uploadedImages as $image) {
                $this->imageUploader->delete($image);
            }
        }

        return $this->render('admin/cardnext/cms/block/form.html.twig', ['form' => $form, 'page' => $page, 'block' => $block]);
    }

    private function addMissingTranslations(CmsPage $page): void
    {
        foreach ($this->entityManager->getRepository(Locale::class)->findBy([], ['code' => 'ASC']) as $locale) {
            if ($locale->getCode() !== null && $page->getTranslation($locale->getCode()) === null) {
                $translation = new CmsPageTranslation();
                $translation->setLocale($locale->getCode());
                $page->addTranslation($translation);
            }
        }
    }

    /** @return array<string, string> */
    private function localeChoices(): array
    {
        $choices = [];
        foreach ($this->entityManager->getRepository(Locale::class)->findBy([], ['code' => 'ASC']) as $locale) {
            if ($locale->getCode() !== null) {
                $choices[$locale->getName() ?? $locale->getCode()] = $locale->getCode();
            }
        }

        return $choices;
    }

    /** @return array<string, string> */
    private function localeNames(): array
    {
        $names = [];
        foreach ($this->entityManager->getRepository(Locale::class)->findBy([], ['code' => 'ASC']) as $locale) {
            if ($locale->getCode() !== null) {
                $names[$locale->getCode()] = $locale->getName() ?? $locale->getCode();
            }
        }

        return $names;
    }

    /** @param array<string, mixed> $configuration */
    private function configurationImage(array $configuration): ?string
    {
        return isset($configuration['image']) && is_string($configuration['image']) ? $configuration['image'] : null;
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return list<string>
     */
    private function configurationImages(array $configuration): array
    {
        $images = [];
        if (($image = $this->configurationImage($configuration)) !== null) {
            $images[] = $image;
        }
        $items = $configuration['items'] ?? null;
        if (is_array($items)) {
            foreach ($items as $item) {
                if (is_array($item) && isset($item['image']) && is_string($item['image'])) {
                    $images[] = $item['image'];
                }
            }
        }

        return array_values(array_unique($images));
    }
}
