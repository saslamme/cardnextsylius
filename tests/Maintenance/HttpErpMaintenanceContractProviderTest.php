<?php

declare(strict_types=1);

namespace App\Tests\Maintenance;

use App\Integration\Erp\Maintenance\HttpErpMaintenanceContractProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpErpMaintenanceContractProviderTest extends TestCase
{
    private const MAP = ['externalId' => 'id', 'erpCustomerNumber' => 'customer', 'serialNumber' => 'serial', 'startsAt' => 'start', 'endsAt' => 'end'];

    public function testMapsConfiguredProductionFieldsWithoutHardCodedNames(): void
    {
        $provider = $this->provider(new MockResponse('[{"id":" ABC ","customer":"1001","serial":" SN1 ","start":"2026-01-01","end":"2026-12-31"}]'));
        $rows = iterator_to_array($provider->fetchAll());

        self::assertCount(1, $rows);
        self::assertSame('ABC', $rows[0]->externalId);
        self::assertSame('SN1', $rows[0]->serialNumber);
    }

    public function testEmptySuccessfulListIsAcceptedWithoutDestructiveSideEffects(): void
    {
        self::assertSame([], iterator_to_array($this->provider(new MockResponse('[]'))->fetchAll()));
    }

    public function testHttpFailureIsPropagated(): void
    {
        $this->expectException(\Throwable::class);
        iterator_to_array($this->provider(new MockResponse('', ['http_code' => 503]))->fetchAll());
    }

    /** @dataProvider invalidRows */
    public function testInvalidRecordIsSkipped(string $json): void
    {
        self::assertSame([], iterator_to_array($this->provider(new MockResponse($json))->fetchAll()));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRows(): iterable
    {
        yield 'missing id' => ['[{"customer":"1","serial":"S","start":"2026-01-01","end":"2026-02-01"}]'];
        yield 'missing customer' => ['[{"id":"1","serial":"S","start":"2026-01-01","end":"2026-02-01"}]'];
        yield 'missing serial' => ['[{"id":"1","customer":"1","start":"2026-01-01","end":"2026-02-01"}]'];
        yield 'missing start' => ['[{"id":"1","customer":"1","serial":"S","end":"2026-02-01"}]'];
        yield 'invalid end' => ['[{"id":"1","customer":"1","serial":"S","start":"2026-01-01","end":"invalid"}]'];
        yield 'reversed dates' => ['[{"id":"1","customer":"1","serial":"S","start":"2027-01-01","end":"2026-01-01"}]'];
    }

    private function provider(MockResponse $response): HttpErpMaintenanceContractProvider
    {
        return new HttpErpMaintenanceContractProvider(new MockHttpClient($response), new NullLogger(), 'https://erp.invalid', '/configured', 'X-Key', 'secret', self::MAP);
    }
}
