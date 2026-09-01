<?php

declare(strict_types=1);

namespace App\ProductImage;

use App\Entity\Product\Product;
use App\Entity\Product\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ProductImageImporter
{
    private const MIME_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const MEDIA_PREFIX = 'media/cardnext/products/';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir,
    ) {
    }

    public function import(string $manifest, string $imageDirectory, bool $dryRun = false, bool $replace = false): ProductImageImportResult
    {
        if (!is_file($manifest) || !is_readable($manifest)) {
            throw new \InvalidArgumentException(sprintf('Manifest "%s" does not exist or is not readable.', $manifest));
        }
        $imageRoot = realpath($imageDirectory);
        if ($imageRoot === false || !is_dir($imageRoot) || !is_readable($imageRoot)) {
            throw new \InvalidArgumentException(sprintf('Image directory "%s" does not exist or is not readable.', $imageDirectory));
        }

        $result = new ProductImageImportResult();
        $handle = fopen($manifest, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open manifest.');
        }

        try {
            $header = fgetcsv($handle, null, ';', '"', '');
            if ($header === false) {
                throw new \InvalidArgumentException('Manifest is empty.');
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
            if ($header !== ['product_code', 'images']) {
                throw new \InvalidArgumentException('Manifest header must be exactly "product_code;images".');
            }
            $row = 1;
            while (($values = fgetcsv($handle, null, ';', '"', '')) !== false) {
                ++$row;
                if ($values === [null]) {
                    continue;
                }
                ++$result->counts['products_in_manifest'];
                $this->processRow((string) ($values[0] ?? ''), (string) ($values[1] ?? ''), $imageRoot, $row, $dryRun, $replace, $result);
            }
        } finally {
            fclose($handle);
        }

        return $result;
    }

    private function processRow(string $code, string $images, string $imageRoot, int $row, bool $dryRun, bool $replace, ProductImageImportResult $result): void
    {
        $code = trim($code);
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $code]);
        if (!$product instanceof Product) {
            ++$result->counts['products_missing'];
            $result->warnings[] = sprintf('Row %d, product "%s": product was not found.', $row, $code);

            return;
        }
        ++$result->counts['products_found'];

        $filenames = array_values(array_filter(array_map('trim', explode('|', $images)), static fn (string $name): bool => $name !== ''));
        $result->counts['images_requested'] += count($filenames);
        if ($replace && $filenames === []) {
            $result->warnings[] = sprintf('Row %d, product "%s": replacement skipped because no images were requested.', $row, $code);

            return;
        }
        $validated = [];
        foreach ($filenames as $position => $filename) {
            $image = $this->validate($code, $filename, $imageRoot, $row, $result);
            if ($image !== null) {
                $validated[] = $image + ['position' => $position];
            }
        }
        // Replacement is all-or-nothing at product level.
        if ($replace && count($validated) !== count($filenames)) {
            $result->warnings[] = sprintf('Row %d, product "%s": replacement skipped because not all requested images are valid.', $row, $code);

            return;
        }

        $slug = strtolower((string) $this->slugger->slug($code));
        $existingPaths = [];
        $maxPosition = -1;
        foreach ($product->getImages() as $existing) {
            if (!$existing instanceof ProductImage) {
                continue;
            }
            if ($existing->getPath() !== null) {
                $existingPaths[$existing->getPath()] = true;
            }
            $maxPosition = max($maxPosition, (int) $existing->getPosition());
        }
        $new = [];
        foreach ($validated as $item) {
            $relative = sprintf('%s%s/%02d-%s.%s', self::MEDIA_PREFIX, $slug, $item['position'] + 1, $item['hash'], $item['extension']);
            $contentAlreadyAssigned = false;
            foreach (array_keys($existingPaths) as $existingPath) {
                if (str_ends_with($existingPath, '-' . $item['hash'] . '.' . $item['extension'])) {
                    $contentAlreadyAssigned = true;

                    break;
                }
            }
            if (isset($existingPaths[$relative]) || $contentAlreadyAssigned) {
                ++$result->counts['images_already_assigned'];
                if (!$replace) {
                    continue;
                }
            }
            ++$result->counts['images_to_create'];
            $new[] = $item + ['relative' => $relative];
        }
        if ($replace) {
            ++$result->counts['products_to_replace'];
        }
        if ($dryRun) {
            return;
        }

        $filesystem = new Filesystem();
        $copied = [];

        try {
            foreach ($new as $item) {
                $destination = $this->projectDir . '/public/' . $item['relative'];
                $filesystem->mkdir(dirname($destination), 0775);
                if (!is_file($destination)) {
                    $filesystem->copy($item['source'], $destination, false);
                    $copied[] = $destination;
                }
            }
            $oldImages = $product->getImages()->toArray();
            $this->entityManager->wrapInTransaction(function () use ($product, $oldImages, $new, $replace, $maxPosition): void {
                if ($replace) {
                    foreach ($oldImages as $oldImage) {
                        $product->removeImage($oldImage);
                        $this->entityManager->remove($oldImage);
                    }
                }
                foreach ($new as $item) {
                    $image = new ProductImage();
                    $image->setPath($item['relative']);
                    $image->setPosition($replace ? $item['position'] : $maxPosition + 1 + $item['position']);
                    // The storefront orders the collection and uses its first image as primary; null is Sylius' gallery-compatible default type.
                    $image->setType(null);
                    $product->addImage($image);
                    $this->entityManager->persist($image);
                }
                $this->entityManager->flush();
            });
            if ($replace) {
                $newPaths = array_fill_keys(array_column($new, 'relative'), true);
                foreach ($oldImages as $oldImage) {
                    $path = $oldImage->getPath();
                    if ($this->isControlledPath($path) && !isset($newPaths[$path])) {
                        $filesystem->remove($this->projectDir . '/public/' . $path);
                    }
                }
            }
        } catch (\Throwable $exception) {
            $filesystem->remove($copied);

            throw new \RuntimeException(sprintf('Product "%s" could not be updated: %s', $code, $exception->getMessage()), 0, $exception);
        }
    }

    /** @return array{source: string, hash: string, extension: string}|null */
    private function validate(string $code, string $filename, string $root, int $row, ProductImageImportResult $result): ?array
    {
        if ($filename !== basename($filename) || str_contains($filename, "\0") || str_contains($filename, '/') || str_contains($filename, '\\') || $filename === '.' || $filename === '..') {
            ++$result->counts['images_invalid'];
            $result->warnings[] = sprintf('Row %d, product "%s", image "%s": paths and directory traversal are not allowed.', $row, $code, $filename);

            return null;
        }
        $candidate = $root . \DIRECTORY_SEPARATOR . $filename;
        $source = realpath($candidate);
        if ($source === false || !str_starts_with($source, $root . \DIRECTORY_SEPARATOR) || !is_file($source) || !is_readable($source)) {
            ++$result->counts['images_missing'];
            $result->warnings[] = sprintf('Row %d, product "%s", image "%s": file is missing or unreadable.', $row, $code, $filename);

            return null;
        }
        $mime = (new \finfo(\FILEINFO_MIME_TYPE))->file($source);
        $size = @getimagesize($source);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime]) || $size === false || $size['mime'] !== $mime) {
            ++$result->counts['images_invalid'];
            $result->warnings[] = sprintf('Row %d, product "%s", image "%s": not a valid JPEG, PNG, or WebP image (detected MIME: %s).', $row, $code, $filename, is_string($mime) ? $mime : 'unknown');

            return null;
        }
        ++$result->counts['images_valid'];
        $hash = hash_file('sha256', $source);
        if ($hash === false) {
            ++$result->counts['images_invalid'];
            --$result->counts['images_valid'];
            $result->warnings[] = sprintf('Row %d, product "%s", image "%s": file could not be hashed.', $row, $code, $filename);

            return null;
        }

        return ['source' => $source, 'hash' => $hash, 'extension' => self::MIME_EXTENSIONS[$mime]];
    }

    private function isControlledPath(?string $path): bool
    {
        return $path !== null && str_starts_with($path, self::MEDIA_PREFIX) && !str_contains($path, '..');
    }
}
