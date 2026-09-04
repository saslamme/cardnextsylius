<?php

declare(strict_types=1);

namespace App\Cms;

final class VideoEmbedResolver
{
    private const YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'];

    private const VIMEO_HOSTS = ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];

    public function resolve(string $provider, string $url, bool $privacyMode = true, bool $showControls = true): ?VideoEmbed
    {
        if (filter_var($url, \FILTER_VALIDATE_URL) === false || parse_url($url, \PHP_URL_SCHEME) !== 'https') {
            return null;
        }

        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));
        $videoId = match ($provider) {
            'youtube' => $this->youtubeId($host, $url),
            'vimeo' => $this->vimeoId($host, $url),
            default => null,
        };

        if ($videoId === null) {
            return null;
        }

        if ($provider === 'youtube') {
            $embedHost = $privacyMode ? 'www.youtube-nocookie.com' : 'www.youtube.com';

            return new VideoEmbed($provider, $videoId, sprintf('https://%s/embed/%s?controls=%d', $embedHost, $videoId, (int) $showControls));
        }

        return new VideoEmbed($provider, $videoId, sprintf('https://player.vimeo.com/video/%s?controls=%d', $videoId, (int) $showControls));
    }

    private function youtubeId(string $host, string $url): ?string
    {
        if (!in_array($host, self::YOUTUBE_HOSTS, true)) {
            return null;
        }

        $path = trim((string) parse_url($url, \PHP_URL_PATH), '/');
        if ($host === 'youtu.be') {
            $candidate = explode('/', $path)[0];
        } elseif (str_starts_with($path, 'shorts/')) {
            $candidate = explode('/', substr($path, 7))[0];
        } elseif ($path === 'watch') {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $candidate = is_string($query['v'] ?? null) ? $query['v'] : '';
        } else {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]{11}$/D', $candidate) === 1 ? $candidate : null;
    }

    private function vimeoId(string $host, string $url): ?string
    {
        if (!in_array($host, self::VIMEO_HOSTS, true)) {
            return null;
        }

        $path = trim((string) parse_url($url, \PHP_URL_PATH), '/');
        $candidate = $host === 'player.vimeo.com' && str_starts_with($path, 'video/') ? substr($path, 6) : $path;

        return preg_match('/^[0-9]+$/D', $candidate) === 1 ? $candidate : null;
    }
}
