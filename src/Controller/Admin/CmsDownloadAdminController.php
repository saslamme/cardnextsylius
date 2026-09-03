<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Cms\CmsDownloadStorage;
use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsDownload;
use App\Entity\Cms\CmsDownloadTranslation;
use App\Entity\Locale\Locale;
use App\Form\Cms\CmsDownloadType;
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
final class CmsDownloadAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CmsDownloadStorage $storage,
    ) {
    }

    #[Route('/admin/cardnext/cms/downloads', name: 'cardnext_admin_cms_downloads', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $builder = $this->entityManager->getRepository(CmsDownload::class)->createQueryBuilder('d')
            ->leftJoin('d.translations', 't')->addSelect('t')
            ->leftJoin('d.channels', 'c')->addSelect('c')->distinct()->orderBy('d.updatedAt', 'DESC');
        if (($search = trim($request->query->getString('q'))) !== '') {
            $builder->andWhere('LOWER(t.title) LIKE :q OR LOWER(d.code) LIKE :q OR LOWER(d.manufacturer) LIKE :q')->setParameter('q', '%' . strtolower($search) . '%');
        }
        foreach (['type' => 'd.type', 'channel' => 'c.code', 'enabled' => 'd.enabled', 'manufacturer' => 'd.manufacturer'] as $parameter => $field) {
            if (($value = $request->query->getString($parameter)) !== '') {
                $builder->andWhere($field . ' = :' . $parameter)->setParameter($parameter, $value);
            }
        }

        return $this->render('admin/cardnext/cms/download/index.html.twig', [
            'downloads' => $builder->getQuery()->getResult(),
            'channels' => $this->entityManager->getRepository(Channel::class)->findBy([], ['code' => 'ASC']),
        ]);
    }

    #[Route('/admin/cardnext/cms/downloads/new', name: 'cardnext_admin_cms_download_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $download = new CmsDownload();
        $this->addMissingTranslations($download);

        return $this->downloadForm($download, $request, true);
    }

    #[Route('/admin/cardnext/cms/downloads/{id}/edit', name: 'cardnext_admin_cms_download_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(CmsDownload $download, Request $request): Response
    {
        $this->addMissingTranslations($download);

        return $this->downloadForm($download, $request, false);
    }

    #[Route('/admin/cardnext/cms/downloads/{id}/delete', name: 'cardnext_admin_cms_download_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(CmsDownload $download, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-cms-download-' . $download->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $storedFile = $download->getFilePath();
        $this->entityManager->remove($download);
        $this->entityManager->flush();
        $this->storage->delete($storedFile);
        $this->addFlash('success', 'Download wurde gelöscht.');

        return $this->redirectToRoute('cardnext_admin_cms_downloads');
    }

    private function downloadForm(CmsDownload $download, Request $request, bool $new): Response
    {
        $old = [
            'path' => $new ? null : $download->getFilePath(),
            'original' => $download->getOriginalFilename(),
            'mime' => $download->getMimeType(),
            'size' => $download->getFileSize(),
        ];
        $form = $this->createForm(CmsDownloadType::class, $download)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $upload = $form->get('uploadedFile')->getData();
            $newStoredFile = null;
            try {
                if ($upload instanceof UploadedFile) {
                    $stored = $this->storage->store($upload);
                    $newStoredFile = $stored['path'];
                    $download->setFilePath($stored['path']);
                    $download->setOriginalFilename($stored['original']);
                    $download->setMimeType($stored['mime']);
                    $download->setFileSize($stored['size']);
                    $download->setExternalUrl(null);
                }
                if ($new) {
                    $this->entityManager->persist($download);
                }
                $this->entityManager->flush();
                if ($old['path'] !== null && $old['path'] !== $download->getFilePath()) {
                    $this->storage->delete($old['path']);
                }
                $this->addFlash('success', $new ? 'Download wurde erstellt.' : 'Download wurde gespeichert.');

                return $this->redirectToRoute('cardnext_admin_cms_download_edit', ['id' => $download->getId()]);
            } catch (\Throwable $exception) {
                $this->storage->delete($newStoredFile);
                $download->setFilePath($old['path']);
                $download->setOriginalFilename($old['original']);
                $download->setMimeType($old['mime']);
                $download->setFileSize($old['size']);
                $form->addError(new FormError('Der Download konnte nicht gespeichert werden: ' . $exception->getMessage()));
            }
        }

        return $this->render('admin/cardnext/cms/download/form.html.twig', [
            'form' => $form,
            'download' => $download,
            'new' => $new,
            'locale_names' => $this->localeNames(),
        ]);
    }

    private function addMissingTranslations(CmsDownload $download): void
    {
        foreach ($this->entityManager->getRepository(Locale::class)->findBy([], ['code' => 'ASC']) as $locale) {
            if ($locale->getCode() !== null && $download->getTranslation($locale->getCode()) === null) {
                $translation = new CmsDownloadTranslation();
                $translation->setLocale($locale->getCode());
                $download->addTranslation($translation);
            }
        }
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
}
