<?php

declare(strict_types=1);

namespace App\Cms;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class CmsImageUploader
{
    private const PREFIX = 'uploads/cms/';
    private const MAX_SIZE = 5 * 1024 * 1024;

    public function __construct(private string $projectDir)
    {
    }

    public function upload(UploadedFile $file): string
    {
        $size = $file->getSize();
        $mime = $file->getMimeType();
        $info = @getimagesize($file->getPathname());
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Nur JPEG-, PNG- oder WebP-Bilder sind erlaubt.'),
        };
        if ($size === false || $size > self::MAX_SIZE || $info === false || $info['mime'] !== $mime) {
            throw new \InvalidArgumentException('Das Bild ist ungültig oder größer als 5 MB.');
        }
        $directory = $this->projectDir . '/public/' . self::PREFIX;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Das Upload-Verzeichnis konnte nicht angelegt werden.');
        }
        $name = bin2hex(random_bytes(20)) . '.' . $extension;
        $file->move($directory, $name);

        return self::PREFIX . $name;
    }

    public function delete(?string $path): void
    {
        if ($path === null || !str_starts_with($path, self::PREFIX) || str_contains(substr($path, strlen(self::PREFIX)), '/')) {
            return;
        }
        $absolute = $this->projectDir . '/public/' . $path;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
