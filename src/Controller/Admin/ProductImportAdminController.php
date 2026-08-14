<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product\ProductImportRun;
use App\Service\CardnextProductCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class ProductImportAdminController extends AbstractController
{
    private const MAX_FILE_SIZE = 20_000_000;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/admin/cardnext/product-import', name: 'cardnext_admin_product_import_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->renderIndex($entityManager);
    }

    #[Route('/admin/cardnext/product-import/preview', name: 'cardnext_admin_product_import_preview', methods: ['POST'])]
    public function preview(
        Request $request,
        EntityManagerInterface $entityManager,
        CardnextProductCsvImporter $importer,
    ): Response {
        if (!$this->isCsrfTokenValid('cardnext-product-import-upload', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('csv');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', 'Bitte eine gültige CSV-Datei auswählen.');

            return $this->redirectToRoute('cardnext_admin_product_import_index');
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            $this->addFlash('error', 'Es sind ausschließlich CSV-Dateien erlaubt.');

            return $this->redirectToRoute('cardnext_admin_product_import_index');
        }

        $size = $file->getSize();
        if ($size !== false && $size > self::MAX_FILE_SIZE) {
            $this->addFlash('error', 'Die CSV-Datei darf maximal 20 MB groß sein.');

            return $this->redirectToRoute('cardnext_admin_product_import_index');
        }

        $token = bin2hex(random_bytes(16));
        $stagingDirectory = $this->stagingDirectory();
        if (!is_dir($stagingDirectory) && !mkdir($stagingDirectory, 0775, true) && !is_dir($stagingDirectory)) {
            throw new \RuntimeException('Import-Verzeichnis konnte nicht erstellt werden.');
        }

        $stagedPath = $stagingDirectory . '/' . $token . '.csv';
        $originalFilename = mb_substr($file->getClientOriginalName(), 0, 255);

        $file->move($stagingDirectory, $token . '.csv');

        $run = new ProductImportRun();
        $run->setOriginalFilename($originalFilename);
        $run->setDryRun(true);
        $run->setStatus(ProductImportRun::STATUS_VALIDATED);
        $run->setUserIdentifier($this->getUser()?->getUserIdentifier());

        try {
            $result = $importer->import(
                $stagedPath,
                true,
                $this->defaultImageDirectory(),
                $this->defaultManufacturerLogoDirectory(),
                $this->defaultDocumentDirectory(),
            );

            $run->applyResult($result);
            $entityManager->persist($run);
            $entityManager->flush();

            return $this->renderIndex(
                $entityManager,
                preview: $result,
                token: $token,
                originalFilename: $originalFilename,
            );
        } catch (\Throwable $exception) {
            $run->markFailed($exception->getMessage());
            $entityManager->persist($run);
            $entityManager->flush();

            @unlink($stagedPath);

            $this->addFlash('error', 'Validierung fehlgeschlagen: ' . $exception->getMessage());

            return $this->redirectToRoute('cardnext_admin_product_import_index');
        }
    }

    #[Route('/admin/cardnext/product-import/run/{token}', name: 'cardnext_admin_product_import_run', methods: ['POST'])]
    public function run(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        CardnextProductCsvImporter $importer,
    ): Response {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('cardnext-product-import-run-' . $token, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $path = $this->stagingDirectory() . '/' . $token . '.csv';
        if (!is_file($path) || !is_readable($path)) {
            $this->addFlash('error', 'Die vorbereitete Importdatei ist nicht mehr vorhanden. Bitte erneut hochladen.');

            return $this->redirectToRoute('cardnext_admin_product_import_index');
        }

        $originalFilename = (string) $request->request->get('original_filename', 'product-import.csv');

        $run = new ProductImportRun();
        $run->setOriginalFilename($originalFilename);
        $run->setDryRun(false);
        $run->setUserIdentifier($this->getUser()?->getUserIdentifier());

        try {
            $result = $importer->import(
                $path,
                false,
                $this->defaultImageDirectory(),
                $this->defaultManufacturerLogoDirectory(),
                $this->defaultDocumentDirectory(),
            );

            $run->setStatus(ProductImportRun::STATUS_SUCCESS);
            $run->applyResult($result);

            $entityManager->persist($run);
            $entityManager->flush();

            @unlink($path);

            $this->addFlash(
                'success',
                sprintf(
                    'Import abgeschlossen: %d Zeilen, %d neue Hersteller, %d neue und %d aktualisierte Produkte.',
                    $result['rows'],
                    $result['manufacturers_created'],
                    $result['products_created'],
                    $result['products_updated'],
                ),
            );
        } catch (\Throwable $exception) {
            $run->markFailed($exception->getMessage());
            $entityManager->persist($run);
            $entityManager->flush();

            $this->addFlash('error', 'Import fehlgeschlagen: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('cardnext_admin_product_import_index');
    }

    #[Route('/admin/cardnext/product-import/template', name: 'cardnext_admin_product_import_template', methods: ['GET'])]
    public function template(): BinaryFileResponse
    {
        $path = $this->projectDir . '/var/import/cardnext/products-template.csv';

        if (!is_file($path)) {
            throw $this->createNotFoundException('CSV-Vorlage nicht gefunden.');
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'cardnext-products-template.csv',
        );

        return $response;
    }

    /**
     * @param array{
     *   rows:int,
     *   manufacturers_created:int,
     *   manufacturers_updated:int,
     *   documents_created:int,
     *   documents_updated:int,
     *   compatibilities_created:int,
     *   compatibilities_updated:int,
     *   price_rules_created:int,
     *   price_rules_updated:int,
     *   customer_price_rules_created:int,
     *   customer_price_rules_updated:int,
     *   products_created:int,
     *   products_updated:int,
     *   variants_created:int,
     *   variants_updated:int,
     *   warnings:list<string>
     * }|null $preview
     */
    private function renderIndex(
        EntityManagerInterface $entityManager,
        ?array $preview = null,
        ?string $token = null,
        ?string $originalFilename = null,
    ): Response {
        $runs = $entityManager
            ->getRepository(ProductImportRun::class)
            ->findBy([], ['createdAt' => 'DESC'], 20);

        return $this->render('admin/cardnext/product_import/index.html.twig', [
            'page_title' => 'Produktimport',
            'runs' => $runs,
            'preview' => $preview,
            'import_token' => $token,
            'original_filename' => $originalFilename,
            'image_directory_available' => is_dir($this->projectDir . '/var/import/cardnext/images'),
            'manufacturer_logo_directory_available' => is_dir($this->projectDir . '/var/import/cardnext/manufacturer-logos'),
            'document_directory_available' => is_dir($this->projectDir . '/var/import/cardnext/documents'),
        ]);
    }

    private function stagingDirectory(): string
    {
        return $this->projectDir . '/var/import/cardnext/admin';
    }

    private function defaultImageDirectory(): ?string
    {
        $directory = $this->projectDir . '/var/import/cardnext/images';

        return is_dir($directory) ? $directory : null;
    }

    private function defaultManufacturerLogoDirectory(): ?string
    {
        $directory = $this->projectDir . '/var/import/cardnext/manufacturer-logos';

        return is_dir($directory) ? $directory : null;
    }

    private function defaultDocumentDirectory(): ?string
    {
        $directory = $this->projectDir . '/var/import/cardnext/documents';

        return is_dir($directory) ? $directory : null;
    }
}
