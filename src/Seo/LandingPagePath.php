<?php

declare(strict_types=1);

namespace App\Seo;

final class LandingPagePath
{
    public static function normalize(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, '?') || str_contains($path, '#')) {
            throw new \InvalidArgumentException('Der Pfad darf weder leer sein noch Query-String oder Fragment enthalten.');
        }

        $path = '/' . ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }
}
