<?php

declare(strict_types=1);

namespace App\Branding;

use App\Entity\Channel\Channel;
use enshrined\svgSanitize\Sanitizer;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ChannelBrandingUploader
{
    private const FILES = [
        'logoFile' => 'setLogoPath',
        'logoDarkFile' => 'setLogoDarkPath',
        'faviconFile' => 'setFaviconPath',
    ];

    public function __construct(private string $projectDir)
    {
    }

    public function upload(Channel $channel): void
    {
        $prepared = [];
        foreach (self::FILES as $property => $setter) {
            $file = $channel->{$property};
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $prepared[] = [$property, $setter, ...$this->prepare($file, $property)];
        }

        if ([] === $prepared) {
            return;
        }

        $directory = $this->projectDir . '/public/uploads/channel-branding';
        $written = [];

        try {
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('Upload directory could not be created.');
            }

            // All files have been checked and sanitized before the first public write.
            foreach ($prepared as [$property, $setter, $contents, $extension]) {
                $name = bin2hex(random_bytes(20)) . '.' . $extension;
                $path = $directory . '/' . $name;
                if (false === file_put_contents($path, $contents, \LOCK_EX)) {
                    throw new \RuntimeException('Upload could not be written.');
                }
                chmod($path, 0644);
                $written[] = [$path, $property, $setter, 'uploads/channel-branding/' . $name];
            }
        } catch (\Throwable $exception) {
            foreach ($written as [$path]) {
                @unlink($path);
            }

            throw new ChannelBrandingUploadException($prepared[count($written)][0] ?? 'logoFile', 'Die Datei konnte nicht gespeichert werden.', $exception);
        }

        foreach ($written as [, $property, $setter, $relativePath]) {
            $channel->{$setter}($relativePath);
            $channel->{$property} = null;
        }
    }

    /** @return array{string, string} */
    private function prepare(UploadedFile $file, string $field): array
    {
        $contents = file_get_contents($file->getPathname());
        if (false === $contents || '' === $contents) {
            throw new ChannelBrandingUploadException($field, 'Die Datei konnte nicht gelesen werden.');
        }

        $mimeType = $file->getMimeType();
        if ('image/svg+xml' === $mimeType || str_contains($contents, '<svg')) {
            return [$this->sanitizeSvg($contents, $field), 'svg'];
        }

        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/jpeg' => 'jpg',
            default => throw new ChannelBrandingUploadException($field, 'Dieses Dateiformat wird nicht unterstützt.'),
        };

        $imageInfo = @getimagesize($file->getPathname());
        if (false === $imageInfo || $mimeType !== $imageInfo['mime']) {
            throw new ChannelBrandingUploadException($field, 'Die Bilddatei ist ungültig.');
        }

        return [$contents, $extension];
    }

    private function sanitizeSvg(string $contents, string $field): string
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $contents)) {
            throw new ChannelBrandingUploadException($field, 'Die SVG-Datei enthält nicht erlaubte Inhalte.');
        }

        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);

        try {
            $clean = $sanitizer->sanitize($contents);
        } catch (\Throwable $exception) {
            throw new ChannelBrandingUploadException($field, 'Die SVG-Datei ist ungültig.', $exception);
        }

        if (!is_string($clean) || '' === trim($clean)) {
            throw new ChannelBrandingUploadException($field, 'Die SVG-Datei ist ungültig.');
        }

        $document = new \DOMDocument();
        if (!@$document->loadXML($clean, \LIBXML_NONET)) {
            throw new ChannelBrandingUploadException($field, 'Die SVG-Datei ist ungültig.');
        }
        $root = $document->documentElement;
        if (!$root instanceof \DOMElement || 'svg' !== strtolower($root->localName ?? '')) {
            throw new ChannelBrandingUploadException($field, 'Die SVG-Datei ist ungültig.');
        }

        // The sanitizer's remote-reference option does not cover every SVG2 `href`
        // variant. Enforce the policy on the resulting DOM as a second layer.
        foreach ((new \DOMXPath($document))->query('//@*') ?: [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);
            if (str_starts_with($name, 'on') ||
                preg_match('/(?:javascript\s*:|(?:url\s*\(\s*["\']?\s*)?(?:https?:)?\/\/)/i', $value) ||
                (in_array($name, ['href', 'src'], true) && '' !== $value && !str_starts_with($value, '#'))
            ) {
                $attribute->ownerElement?->removeAttributeNode($attribute);
            }
        }

        $clean = $document->saveXML();
        if (false === $clean || '' === trim($clean)) {
            throw new ChannelBrandingUploadException($field, 'Die SVG-Datei ist ungültig.');
        }

        return $clean;
    }
}
