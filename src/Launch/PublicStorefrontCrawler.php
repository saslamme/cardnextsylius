<?php

declare(strict_types=1);

namespace App\Launch;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PublicStorefrontCrawler
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function crawl(string $baseUrl, int $maxPages = 250): LaunchCheckResult
    {
        $result = new LaunchCheckResult();
        $baseUrl = rtrim($baseUrl, '/') . '/';
        $origin = $this->origin($baseUrl);
        if ($origin === null || !in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $result->issue('critical', 'http', 'INVALID_BASE_URL', 'The base URL must be an absolute HTTP(S) URL.', ['base_url' => $baseUrl]);

            return $result;
        }

        $queue = [$baseUrl];
        $visited = [];
        while ($queue !== [] && count($visited) < max(1, $maxPages)) {
            $url = array_shift($queue);
            if (isset($visited[$url])) {
                continue;
            }
            $visited[$url] = true;
            $response = $this->request($url, $result, null);
            if ($response === null || $response['status'] >= 400 || !str_contains($response['contentType'], 'text/html')) {
                continue;
            }

            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $loaded = $document->loadHTML($response['body'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if (!$loaded) {
                $result->issue('warning', 'http', 'HTML_PARSE_FAILED', sprintf('Could not parse HTML returned by %s.', $url), ['url' => $url]);
                continue;
            }

            foreach ([['a', 'href', true], ['link', 'href', false], ['script', 'src', false], ['img', 'src', false], ['source', 'src', false]] as [$tag, $attribute, $crawl]) {
                foreach ($document->getElementsByTagName($tag) as $node) {
                    $target = $this->resolveUrl($url, trim($node->getAttribute($attribute)));
                    if ($target === null || $this->origin($target) !== $origin || isset($visited[$target])) {
                        continue;
                    }
                    if ($crawl) {
                        $queue[] = $target;
                    } else {
                        $visited[$target] = true;
                        $this->request($target, $result, $url);
                    }
                }
            }
        }

        if ($queue !== []) {
            $result->issue('warning', 'http', 'CRAWL_LIMIT_REACHED', sprintf('Stopped after checking %d URLs.', count($visited)), ['max_pages' => $maxPages]);
        }

        return $result;
    }

    /** @return array{status: int, contentType: string, body: string}|null */
    private function request(string $url, LaunchCheckResult $result, ?string $source): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 15, 'max_redirects' => 10, 'headers' => ['Accept' => 'text/html,application/xhtml+xml,image/*,*/*;q=0.8']]);
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            $result->issue('critical', 'http', 'HTTP_TRANSPORT_ERROR', sprintf('GET %s failed: %s', $url, $exception->getMessage()), array_filter(['url' => $url, 'source_url' => $source]));

            return null;
        }

        if ($status >= 400) {
            $result->issue('critical', 'http', sprintf('HTTP_%d', $status), sprintf('GET %s returned %d.', $url, $status), array_filter(['url' => $url, 'source_url' => $source]));
        }

        return ['status' => $status, 'contentType' => $headers['content-type'][0] ?? '', 'body' => $body];
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme'] . '://' . $parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    private function resolveUrl(string $source, string $target): ?string
    {
        if ($target === '' || $target[0] === '#' || preg_match('{^(?:mailto|tel|javascript|data):}i', $target)) {
            return null;
        }
        if (str_starts_with($target, '//')) {
            $target = (string) parse_url($source, PHP_URL_SCHEME) . ':' . $target;
        } elseif (!preg_match('{^https?://}i', $target)) {
            $origin = $this->origin($source);
            if ($origin === null) {
                return null;
            }
            $target = str_starts_with($target, '/') ? $origin . $target : $origin . rtrim(dirname((string) parse_url($source, PHP_URL_PATH)), '/') . '/' . $target;
        }
        $parts = parse_url($target);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }

        return strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '') . implode('/', $segments) . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
