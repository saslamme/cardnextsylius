<?php

declare(strict_types=1);

namespace App\Tests\Freshdesk;

use App\Integration\Freshdesk\Exception\FreshdeskAuthenticationException;
use App\Integration\Freshdesk\Exception\FreshdeskRateLimitException;
use App\Integration\Freshdesk\Exception\FreshdeskUnavailableException;
use App\Integration\Freshdesk\HttpFreshdeskClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpFreshdeskClientTest extends TestCase
{
    private const API_KEY = 'super-secret-api-key';

    public function testConnectivityCheckUsesReadOnlyTicketEndpoint(): void
    {
        $response = new MockResponse('[]', [
            'http_code' => 200,
            'response_headers' => ['content-type: application/json'],
        ]);
        $httpClient = new MockHttpClient($response, 'https://cardnext.freshdesk.com');
        $client = $this->client($httpClient);

        $client->checkConnection();

        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://cardnext.freshdesk.com/api/v2/tickets?per_page=1', $response->getRequestUrl());
    }

    /** @param class-string<\Throwable> $expectedException */
    #[DataProvider('failedResponses')]
    public function testConnectivityCheckMapsFailuresWithoutExposingApiKey(int $status, string $expectedException): void
    {
        $client = $this->client(new MockHttpClient(new MockResponse(
            '{"code":"access_denied","description":"Request failed"}',
            ['http_code' => $status, 'response_headers' => ['content-type: application/json']],
        )));

        try {
            $client->checkConnection();
            self::fail('The connectivity check should have failed.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf($expectedException, $exception);
            self::assertStringNotContainsString(self::API_KEY, $exception->getMessage());
        }
    }

    /** @return iterable<string, array{int, class-string<\Throwable>}> */
    public static function failedResponses(): iterable
    {
        yield 'unauthorized' => [401, FreshdeskAuthenticationException::class];
        yield 'forbidden' => [403, FreshdeskAuthenticationException::class];
        yield 'rate limited' => [429, FreshdeskRateLimitException::class];
        yield 'server error' => [500, FreshdeskUnavailableException::class];
        yield 'service unavailable' => [503, FreshdeskUnavailableException::class];
    }

    private function client(MockHttpClient $httpClient): HttpFreshdeskClient
    {
        return new HttpFreshdeskClient($httpClient, 'https://cardnext.freshdesk.com', self::API_KEY);
    }
}
