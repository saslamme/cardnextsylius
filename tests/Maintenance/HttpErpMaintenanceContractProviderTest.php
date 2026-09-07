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
    private const MAP = [
        'externalId' => 'contractId',
        'erpCustomerNumber' => 'customerNumber',
        'startsAt' => 'contractStart',
        'endsAt' => 'contractEnd',
        'printerModel' => 'printerModel',
        'contractReference' => 'referenceNumber',
        'serialNumbers' => 'serialNumbers',
    ];

    public function testMapsProductionContractWithMultipleDevices(): void
    {
        $json = <<<'JSON'
[
  {
    "contractId": 1,
    "customerNumber": "221291",
    "contractStart": "2027-08-18T00:00:00",
    "contractEnd": "2030-08-18T00:00:00",
    "printerModel": "Identcover Premium (Jahrespauschale)",
    "referenceNumber": "SA-1244",
    "serialNumbers": ["883023120019", "883023120021"]
  }
]
JSON;
        $rows = iterator_to_array($this->provider(new MockResponse($json))->fetchAll());

        self::assertCount(1, $rows);
        self::assertSame('1', $rows[0]->externalId);
        self::assertSame('221291', $rows[0]->erpCustomerNumber);
        self::assertSame(['883023120019', '883023120021'], $rows[0]->serialNumbers);
        self::assertSame('Identcover Premium (Jahrespauschale)', $rows[0]->printerModel);
        self::assertSame('SA-1244', $rows[0]->contractReference);
        self::assertEquals(new \DateTimeImmutable('2027-08-18T00:00:00'), $rows[0]->startsAt);
        self::assertEquals(new \DateTimeImmutable('2030-08-18T00:00:00'), $rows[0]->endsAt);
    }

    public function testSerialNumbersAreTrimmedDeduplicatedAndKeepTheirOrder(): void
    {
        $json = '[{"contractId":"1","customerNumber":"001","contractStart":"2026-01-01","contractEnd":"2026-12-31","serialNumbers":[" 12345 ","12345","","67890"]}]';
        $rows = iterator_to_array($this->provider(new MockResponse($json))->fetchAll());

        self::assertSame(['12345', '67890'], $rows[0]->serialNumbers);
        self::assertSame('001', $rows[0]->erpCustomerNumber);
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
        yield 'missing id' => ['[{"customerNumber":"1","serialNumbers":["S"],"contractStart":"2026-01-01","contractEnd":"2026-02-01"}]'];
        yield 'missing customer' => ['[{"contractId":"1","serialNumbers":["S"],"contractStart":"2026-01-01","contractEnd":"2026-02-01"}]'];
        yield 'missing serial numbers' => ['[{"contractId":"1","customerNumber":"1","contractStart":"2026-01-01","contractEnd":"2026-02-01"}]'];
        yield 'serial numbers is not an array' => ['[{"contractId":"1","customerNumber":"1","serialNumbers":"S","contractStart":"2026-01-01","contractEnd":"2026-02-01"}]'];
        yield 'empty serial numbers' => ['[{"contractId":"1","customerNumber":"1","serialNumbers":[],"contractStart":"2026-01-01","contractEnd":"2026-02-01"}]'];
        yield 'blank serial numbers' => ['[{"contractId":"1","customerNumber":"1","serialNumbers":[" "],"contractStart":"2026-01-01","contractEnd":"2026-02-01"}]'];
        yield 'invalid serial number' => ['[{"contractId":"1","customerNumber":"1","serialNumbers":[{}],"contractStart":"2026-01-01","contractEnd":"2026-02-01"}]'];
        yield 'missing start' => ['[{"contractId":"1","customerNumber":"1","serialNumbers":["S"],"contractEnd":"2026-02-01"}]'];
        yield 'invalid end' => ['[{"contractId":"1","customerNumber":"1","serialNumbers":["S"],"contractStart":"2026-01-01","contractEnd":"invalid"}]'];
        yield 'reversed dates' => ['[{"contractId":"1","customerNumber":"1","serialNumbers":["S"],"contractStart":"2027-01-01","contractEnd":"2026-01-01"}]'];
    }

    private function provider(MockResponse $response): HttpErpMaintenanceContractProvider
    {
        return new HttpErpMaintenanceContractProvider(new MockHttpClient($response), new NullLogger(), 'https://erp.invalid', '/configured', 'X-Key', 'secret', self::MAP);
    }
}
