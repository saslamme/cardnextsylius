<?php

declare(strict_types=1);

namespace App\Tests\Maintenance;

use App\Integration\Erp\Maintenance\HttpErpMaintenanceContractProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpErpMaintenanceContractProviderTest extends TestCase
{
    public function testMapsIdbMasterWrapperFixture(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/Fixtures/idbmaster-service-contract.json');
        $rows = iterator_to_array($this->provider(new MockResponse($json))->fetchAll());

        self::assertCount(2, $rows);
        self::assertSame('4711', $rows[0]->externalId);
        self::assertSame('221291', $rows[0]->erpCustomerNumber);
        self::assertSame(['883023120019', '883023120021'], $rows[0]->serialNumbers);
        self::assertSame('Identcover Premium', $rows[0]->printerModel);
        self::assertSame('SA-1244', $rows[0]->contractReference);
        self::assertSame('2027-08-18', $rows[0]->startsAt->format('Y-m-d'));
        self::assertSame('2030-08-18', $rows[0]->endsAt->format('Y-m-d'));
        self::assertSame('2026-09-08 14:30:00', $rows[0]->sourceUpdatedAt?->format('Y-m-d H:i:s'));
        self::assertNull($rows[1]->printerModel);
        self::assertNull($rows[1]->contractReference);
    }

    public function testDirectListAndLegacyFieldMapRemainSupported(): void
    {
        $json = '[{"legacyId":"C-1","legacyCustomer":"001","legacyStart":"2026-01-01","legacyEnd":"2026-12-31","legacySerials":[" A ","A","B"]}]';
        $map = ['externalId' => 'legacyId', 'erpCustomerNumber' => 'legacyCustomer', 'startsAt' => 'legacyStart', 'endsAt' => 'legacyEnd', 'serialNumbers' => 'legacySerials'];
        $rows = iterator_to_array($this->provider(new MockResponse($json), fieldMap: $map)->fetchAll());

        self::assertSame(['A', 'B'], $rows[0]->serialNumbers);
        self::assertSame('001', $rows[0]->erpCustomerNumber);
    }

    public function testStableFallbackIdIsUsedOnlyWithoutIdOrContractNumber(): void
    {
        $json = '{"data":[{"customer_number":"1","start_date":"2026-01-01","end_date":"2026-12-31","serial_number":"S"}]}';
        $first = iterator_to_array($this->provider(new MockResponse($json))->fetchAll())[0];
        $second = iterator_to_array($this->provider(new MockResponse($json))->fetchAll())[0];

        self::assertSame($first->externalId, $second->externalId);
        self::assertStringStartsWith('generated-', $first->externalId);
    }

    /**
     * @dataProvider invalidRows
     *
     * @param array<string, mixed> $invalid
     */
    public function testInvalidRecordIsSkippedWhileLaterValidRecordIsImported(array $invalid): void
    {
        $valid = ['contract_id' => 2, 'customer_number' => '22', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01', 'serial_number' => 'OK'];
        $rows = iterator_to_array($this->provider(new MockResponse(json_encode(['contracts' => [$invalid, $valid]], \JSON_THROW_ON_ERROR)))->fetchAll());

        self::assertCount(1, $rows);
        self::assertSame('2', $rows[0]->externalId);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidRows(): iterable
    {
        $base = ['contract_id' => 1, 'customer_number' => '1', 'start_date' => '2026-01-01', 'end_date' => '2026-02-01', 'serial_number' => 'S'];
        foreach ([
            'missing customer' => 'customer_number',
            'missing serial' => 'serial_number',
            'invalid start' => 'start_date',
            'invalid end' => 'end_date',
        ] as $name => $field) {
            $row = $base;
            $row[$field] = str_starts_with($name, 'invalid') ? 'not-a-date' : '';
            yield $name => [$row];
        }
        yield 'end before start' => [[...$base, 'start_date' => '2027-01-01']];
        yield 'invalid serial item' => [[...$base, 'serial_number' => [[]]]];
    }

    public function testUnsupportedSchemaHasSpecificError(): void
    {
        $this->expectExceptionMessage('Unsupported idbMaster maintenance-contract response structure.');
        iterator_to_array($this->provider(new MockResponse('{"result":{"items":[]}}'))->fetchAll());
    }

    public function testFullUrlTakesPrecedenceAndEmptyAuthAddsNoHeader(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('https://full.invalid/contracts.json', $url);
            self::assertArrayNotHasKey('X-Api-Key', $options['headers']);

            return new MockResponse('[]');
        });
        iterator_to_array($this->providerWithClient($client, url: 'https://full.invalid/contracts.json')->fetchAll());
    }

    public function testLegacyUrlAndOptionalAuthHeader(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('https://erp.invalid/configured', $url);
            self::assertSame('X-Api-Key: secret', $options['normalized_headers']['x-api-key'][0]);

            return new MockResponse('[]');
        });
        iterator_to_array($this->providerWithClient($client, authHeader: 'X-Api-Key', authValue: 'secret')->fetchAll());
    }

    public function testHttpFailureIsPropagated(): void
    {
        $this->expectException(\Throwable::class);
        iterator_to_array($this->provider(new MockResponse('', ['http_code' => 503]))->fetchAll());
    }

    /** @param array<string, string> $fieldMap */
    private function provider(MockResponse $response, array $fieldMap = []): HttpErpMaintenanceContractProvider
    {
        return $this->providerWithClient(new MockHttpClient($response), fieldMap: $fieldMap);
    }

    /** @param array<string, string> $fieldMap */
    private function providerWithClient(HttpClientInterface $client, string $url = '', string $authHeader = '', string $authValue = '', array $fieldMap = []): HttpErpMaintenanceContractProvider
    {
        return new HttpErpMaintenanceContractProvider($client, new NullLogger(), $url, 'https://erp.invalid', '/configured', $authHeader, $authValue, $fieldMap);
    }
}
