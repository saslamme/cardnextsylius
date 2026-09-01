<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\CustomerImport\LegacyCustomerImporter;
use App\CustomerImport\LegacyCustomerImportOptions;
use App\Entity\Channel\Channel;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class CustomerImportController extends AbstractController
{
    private const MAX_FILE_SIZE = 20_000_000;

    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    #[Route('/admin/cardnext/customer-import', name: 'cardnext_admin_customer_import', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->renderPage($entityManager);
    }

    #[Route('/admin/cardnext/customer-import/preview', name: 'cardnext_admin_customer_import_preview', methods: ['POST'])]
    public function preview(Request $request, EntityManagerInterface $entityManager, LegacyCustomerImporter $importer): Response
    {
        if (!$this->isCsrfTokenValid('customer-import-preview', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        /** @var UploadedFile|null $file */
        $file = $request->files->get('customer_file');
        $channelCode = trim((string) $request->request->get('channel'));
        /** @var Channel|null $channel */
        $channel = $entityManager->getRepository(Channel::class)->findOneBy(['code' => $channelCode]);
        $encoding = (string) $request->request->get('encoding', 'ISO-8859-1');
        if (!$file instanceof UploadedFile || !$file->isValid() || ($file->getSize() !== false && $file->getSize() > self::MAX_FILE_SIZE)) {
            $this->addFlash('error', 'Bitte eine gültige Importdatei bis maximal 20 MB auswählen.');

            return $this->redirectToRoute('cardnext_admin_customer_import');
        }
        if (!$channel instanceof Channel || !in_array(strtoupper($encoding), ['ISO-8859-1', 'UTF-8', 'WINDOWS-1252'], true)) {
            $this->addFlash('error', 'Verkaufskanal und Zeichensatz müssen gültig sein.');

            return $this->redirectToRoute('cardnext_admin_customer_import');
        }

        $token = bin2hex(random_bytes(24));
        $directory = $this->stagingDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Das geschützte Importverzeichnis konnte nicht erstellt werden.');
        }
        $path = $directory . '/' . $token . '.data';
        $file->move($directory, $token . '.data');
        $updateExisting = $request->request->getBoolean('update_existing');
        file_put_contents($directory . '/' . $token . '.json', json_encode(['channel' => $channelCode, 'encoding' => $encoding, 'updateExisting' => $updateExisting, 'createdAt' => time()], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT), \LOCK_EX);

        try {
            $result = $importer->import($path, new LegacyCustomerImportOptions($channel, $encoding, true, $updateExisting));
        } catch (\Throwable $exception) {
            @unlink($path);
            @unlink($directory . '/' . $token . '.json');
            $this->addFlash('error', 'Testlauf fehlgeschlagen: ' . $exception->getMessage());

            return $this->redirectToRoute('cardnext_admin_customer_import');
        }

        return $this->renderPage($entityManager, $result, $token, $channelCode, $encoding);
    }

    #[Route('/admin/cardnext/customer-import/run/{token}', name: 'cardnext_admin_customer_import_run', methods: ['POST'])]
    public function run(string $token, Request $request, EntityManagerInterface $entityManager, LegacyCustomerImporter $importer): Response
    {
        if (!preg_match('/^[a-f0-9]{48}$/D', $token) || !$this->isCsrfTokenValid('customer-import-run-' . $token, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $path = $this->stagingDirectory() . '/' . $token . '.data';
        $metadataPath = $this->stagingDirectory() . '/' . $token . '.json';
        if (!is_file($path) || !is_file($metadataPath)) {
            throw $this->createNotFoundException('Der Testlauf ist abgelaufen.');
        }
        /** @var array{channel:string, encoding:string, updateExisting:bool, createdAt:int} $metadata */
        $metadata = json_decode((string) file_get_contents($metadataPath), true, 512, \JSON_THROW_ON_ERROR);
        if ($metadata['createdAt'] < time() - 86400) {
            @unlink($path);
            @unlink($metadataPath);

            throw $this->createNotFoundException('Der Testlauf ist abgelaufen.');
        }
        /** @var Channel|null $channel */
        $channel = $entityManager->getRepository(Channel::class)->findOneBy(['code' => $metadata['channel']]);
        if (!$channel instanceof Channel) {
            throw $this->createNotFoundException('Der Verkaufskanal existiert nicht mehr.');
        }
        $result = $importer->import($path, new LegacyCustomerImportOptions($channel, $metadata['encoding'], false, $metadata['updateExisting']));
        @unlink($path);
        @unlink($metadataPath);
        $this->addFlash('success', sprintf('Import abgeschlossen: %d neu, %d aktualisiert, %d Konflikte.', $result->created, $result->updated, $result->conflicts));

        return $this->redirectToRoute('cardnext_admin_customer_import');
    }

    private function renderPage(EntityManagerInterface $entityManager, mixed $preview = null, ?string $token = null, ?string $channelCode = null, ?string $encoding = null): Response
    {
        return $this->render('admin/cardnext/customer_import/index.html.twig', ['channels' => $entityManager->getRepository(Channel::class)->findBy([], ['enabled' => 'DESC', 'code' => 'ASC']), 'preview' => $preview, 'import_token' => $token, 'channel_code' => $channelCode, 'encoding' => $encoding]);
    }

    private function stagingDirectory(): string
    {
        return $this->projectDir . '/var/import/cardnext/customers';
    }
}
