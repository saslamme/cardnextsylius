<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product\Manufacturer;
use App\Entity\Product\ProductDocument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class CardnextMediaStorage
{
    private const PUBLIC_PREFIX = 'media/cardnext/';

    public function __construct(
        private KernelInterface $kernel,
        private SluggerInterface $slugger,
    ) {
    }

    public function uploadManufacturerLogo(Manufacturer $manufacturer, UploadedFile $file): void
    {
        $extension = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Nicht unterstütztes Logo-Format.'),
        };

        $directory = self::PUBLIC_PREFIX . 'manufacturers';
        $filename = sprintf(
            '%s-%s.%s',
            strtolower((string) $this->slugger->slug($manufacturer->getCode())),
            bin2hex(random_bytes(6)),
            $extension,
        );

        $this->move($file, $directory, $filename, $manufacturer->getLogoPath());
        $manufacturer->setLogoPath($directory . '/' . $filename);
    }

    public function uploadProductDocument(ProductDocument $document, UploadedFile $file): void
    {
        // Read all metadata BEFORE move(). UploadedFile still points to PHP's
        // temporary upload path; after move() that path no longer exists.
        $originalFilename = mb_substr($file->getClientOriginalName(), 0, 255);
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        $productCode = strtolower((string) $this->slugger->slug($document->getProduct()->getCode()));
        $directory = self::PUBLIC_PREFIX . 'product-documents/' . $productCode;
        $filename = sprintf(
            '%s-%s.pdf',
            strtolower((string) $this->slugger->slug($document->getType())),
            bin2hex(random_bytes(8)),
        );

        $oldPath = $document->getFilePath();
        $this->move($file, $directory, $filename, $oldPath);

        $document->setFilePath($directory . '/' . $filename);
        $document->setOriginalFilename($originalFilename);
        $document->setMimeType($mimeType);
        $document->setFileSize($fileSize ?: null);
    }

    public function removeManufacturerLogo(Manufacturer $manufacturer): void
    {
        $this->removePublicFile($manufacturer->getLogoPath());
        $manufacturer->setLogoPath(null);
    }

    public function removeProductDocument(ProductDocument $document): void
    {
        $this->removePublicFile($document->getFilePath());
    }

    private function move(UploadedFile $file, string $relativeDirectory, string $filename, ?string $oldPath): void
    {
        $filesystem = new Filesystem();
        $absoluteDirectory = $this->kernel->getProjectDir() . '/public/' . $relativeDirectory;
        $filesystem->mkdir($absoluteDirectory, 0775);

        $file->move($absoluteDirectory, $filename);

        if ($oldPath !== null && $oldPath !== $relativeDirectory . '/' . $filename) {
            $this->removePublicFile($oldPath);
        }
    }

    private function removePublicFile(?string $relativePath): void
    {
        if ($relativePath === null || !str_starts_with($relativePath, self::PUBLIC_PREFIX)) {
            return;
        }

        $absolutePath = $this->kernel->getProjectDir() . '/public/' . ltrim($relativePath, '/');
        if (is_file($absolutePath)) {
            (new Filesystem())->remove($absolutePath);
        }
    }
}
