<?php

declare(strict_types=1);

namespace App\Launch;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class StorefrontSmokeTester
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /** @param list<string> $paths */
    public function test(string $baseUrl, array $paths): LaunchCheckResult
    {
        $result = new LaunchCheckResult();
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $result->issue('critical', 'http', 'INVALID_BASE_URL', 'The base URL must be an absolute HTTP(S) URL.', ['base_url' => $baseUrl]);

            return $result;
        }
        foreach ($paths as $path) {
            $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
            try {
                $response = $this->httpClient->request('GET', $url, ['timeout' => 15, 'max_redirects' => 10]);
                $status = $response->getStatusCode();
                $body = $response->getContent(false);
            } catch (TransportExceptionInterface $exception) {
                $result->issue('critical', 'http', 'HTTP_TRANSPORT_ERROR', sprintf('GET %s failed: %s', $path, $exception->getMessage()), ['url' => $url]);
                continue;
            }
            if ($status < 200 || $status >= 400) {
                $result->issue('critical', 'http', sprintf('HTTP_%d', $status), sprintf('GET %s returned %d.', $path, $status), ['url' => $url]);
            } elseif ($body === '') {
                $result->issue('critical', 'http', 'EMPTY_RESPONSE', sprintf('GET %s returned an empty response.', $path), ['url' => $url]);
            } else {
                $result->issue('info', 'http', 'HTTP_OK', sprintf('GET %s returned %d.', $path, $status), ['url' => $url]);
            }
        }

        return $result;
    }
}
