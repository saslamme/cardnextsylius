<?php

declare(strict_types=1);

namespace App\Tests\Launch;

use App\Launch\LaunchCheckResult;
use App\Launch\LaunchIssue;
use App\Launch\PublicStorefrontCrawler;
use App\Launch\StorefrontSmokeTester;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LaunchChecksTest extends TestCase
{
    public function testResultFailsOnlyForCriticalIssuesAndSerializesContext(): void
    {
        $result = new LaunchCheckResult();
        $result->add(new LaunchIssue('warning', 'asset', 'MISSING', 'Missing asset.', ['path' => 'image.jpg']));
        self::assertFalse($result->hasCriticalIssues());

        $result->issue('critical', 'http', 'HTTP_500', 'Server error.');
        self::assertTrue($result->hasCriticalIssues());
        self::assertSame(1, $result->jsonSerialize()['counts']['critical']);
        self::assertSame('image.jpg', $result->jsonSerialize()['issues'][0]->context['path']);
    }

    public function testSmokeTesterOnlyUsesGetAndReportsFailedResponses(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = [$method, $url];

            return new MockResponse(str_ends_with($url, '/broken') ? 'failure' : 'ok', ['http_code' => str_ends_with($url, '/broken') ? 500 : 200]);
        });
        $result = (new StorefrontSmokeTester($client))->test('https://shop.example', ['/', '/broken']);

        self::assertSame([['GET', 'https://shop.example/'], ['GET', 'https://shop.example/broken']], $requests);
        self::assertTrue($result->hasCriticalIssues());
        self::assertSame('HTTP_500', $result->issues()[1]->code);
    }

    public function testCrawlerStaysOnOriginAndChecksLinkedAssets(): void
    {
        $requested = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requested): MockResponse {
            $requested[] = $url;
            if ($url === 'https://shop.example/') {
                return new MockResponse('<a href="/catalog">Catalog</a><a href="https://outside.example/no">Outside</a><img src="/missing.jpg">', ['response_headers' => ['content-type: text/html']]);
            }

            return new MockResponse(str_ends_with($url, 'missing.jpg') ? '' : '<html>ok</html>', ['http_code' => str_ends_with($url, 'missing.jpg') ? 404 : 200, 'response_headers' => ['content-type: text/html']]);
        });
        $result = (new PublicStorefrontCrawler($client))->crawl('https://shop.example', 10);

        self::assertContains('https://shop.example/catalog', $requested);
        self::assertContains('https://shop.example/missing.jpg', $requested);
        self::assertNotContains('https://outside.example/no', $requested);
        self::assertSame('HTTP_404', $result->issues()[0]->code);
        self::assertSame('https://shop.example/', $result->issues()[0]->context['source_url']);
    }
}
