<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\Content\ChannelHomepageContent;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ChannelHomepageImageUploader
{
    private const SLOTS = [
        'heroImageFile' => ['getHeroImagePath', 'setHeroImagePath', 'isRemoveHeroImage'],
        'introImageFile' => ['getIntroImagePath', 'setIntroImagePath', 'isRemoveIntroImage'],
        'ctaImageFile' => ['getCtaImagePath', 'setCtaImagePath', 'isRemoveCtaImage'],
    ];

    private const MAX_SIZE = 5 * 1024 * 1024;

    private const PREFIX = 'uploads/channel-homepage/';

    public function __construct(private string $projectDir)
    {
    }

    public function upload(ChannelHomepageContent $content): void
    {
        $prepared = [];
        foreach (self::SLOTS as $field => [$getter, $setter, $removeGetter]) {
            $file = $content->{'get' . ucfirst($field)}();
            if ($file instanceof UploadedFile) {
                $prepared[$field] = [$setter, $getter, ...$this->prepare($file, $field)];
            }
        }

        $directory = $this->projectDir . '/public/' . self::PREFIX;
        $written = [];

        try {
            if ($prepared !== [] && !is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('Upload directory could not be created.');
            }
            foreach ($prepared as $field => [$setter, $getter, $contents, $extension]) {
                $name = bin2hex(random_bytes(20)) . '.' . $extension;
                $absolute = $directory . $name;
                if (file_put_contents($absolute, $contents, \LOCK_EX) === false) {
                    throw new \RuntimeException('Upload could not be written.');
                }
                chmod($absolute, 0644);
                $written[$field] = [$absolute, $setter, $getter, self::PREFIX . $name];
            }
        } catch (\Throwable $exception) {
            foreach ($written as [$absolute]) {
                @unlink($absolute);
            }

            throw new ChannelHomepageImageUploadException(array_key_first(array_diff_key($prepared, $written)) ?? 'heroImageFile', 'Das Bild konnte nicht gespeichert werden.', $exception);
        }

        foreach (self::SLOTS as $field => [$getter, $setter, $removeGetter]) {
            $oldPath = $content->{$getter}();
            if (isset($written[$field])) {
                [, , , $newPath] = $written[$field];
                $content->{$setter}($newPath);
                $content->{'set' . ucfirst($field)}(null);
                $this->deleteCustom($oldPath);
            } elseif ($content->{$removeGetter}()) {
                $content->{$setter}(null);
                $this->deleteCustom($oldPath);
            }
        }
    }

    /** @return array{string, string} */
    private function prepare(UploadedFile $file, string $field): array
    {
        $size = $file->getSize();
        if ($size === false || $size > self::MAX_SIZE) {
            throw new ChannelHomepageImageUploadException($field, 'Das Bild darf maximal 5 MB groß sein.');
        }
        $contents = file_get_contents($file->getPathname());
        $info = @getimagesize($file->getPathname());
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw new ChannelHomepageImageUploadException($field, 'Nur JPEG-, PNG- oder WebP-Bilder sind erlaubt.'),
        };
        if ($contents === false || $contents === '' || $info === false || $info['mime'] !== $mime) {
            throw new ChannelHomepageImageUploadException($field, 'Die Bilddatei ist ungültig.');
        }

        return [$contents, $extension];
    }

    private function deleteCustom(?string $path): void
    {
        if ($path === null || !str_starts_with($path, self::PREFIX) || str_contains(substr($path, strlen(self::PREFIX)), '/')) {
            return;
        }
        $file = $this->projectDir . '/public/' . $path;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
