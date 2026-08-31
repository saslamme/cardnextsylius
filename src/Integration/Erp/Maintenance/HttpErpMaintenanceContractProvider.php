<?php

declare(strict_types=1);

namespace App\Integration\Erp\Maintenance;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpErpMaintenanceContractProvider implements ErpMaintenanceContractProviderInterface
{
    /** @param array<string, string> $fieldMap */
    public function __construct(private HttpClientInterface $httpClient, private LoggerInterface $logger, private string $baseUri, private string $endpoint, private string $authHeader, private string $authValue, private array $fieldMap)
    {
    }

    public function fetchAll(): iterable
    {
        if ($this->baseUri === '' || $this->endpoint === '' || $this->fieldMap === []) {
            throw new \RuntimeException('Production ERP maintenance-contract endpoint and field mapping are not configured.');
        }
        $headers = $this->authHeader !== '' && $this->authValue !== '' ? [$this->authHeader => $this->authValue] : [];
        $response = $this->httpClient->request('GET', rtrim($this->baseUri, '/') . '/' . ltrim($this->endpoint, '/'), ['headers' => $headers, 'timeout' => 30, 'max_duration' => 35]);
        $rows = $response->toArray();
        if (!array_is_list($rows)) {
            throw new \UnexpectedValueException('ERP response must be a JSON list.');
        }
        foreach ($rows as $offset => $row) {
            try {
                if (!is_array($row)) {
                    throw new \UnexpectedValueException('Record is not an object.');
                } yield $this->map($row);
            } catch (\Throwable $error) {
                $this->logger->warning('Invalid ERP maintenance contract skipped.', ['recordOffset' => $offset, 'errorType' => $error::class]);
            }
        }
    }

    /** @param array<mixed> $row */
    private function map(array $row): ErpMaintenanceContractData
    {
        $value = fn (string $name): mixed => $row[$this->fieldMap[$name] ?? ''] ?? null;
        $id = $this->requiredString($value('externalId'));
        $customer = $this->requiredString($value('erpCustomerNumber'));
        $serial = $this->requiredString($value('serialNumber'));
        $starts = $this->date($value('startsAt'));
        $ends = $this->date($value('endsAt'));
        if ($ends < $starts) {
            throw new \UnexpectedValueException('ERP contract date range is invalid.');
        }
        $optional = static fn (mixed $v): ?string => is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;
        $source = $optional($value('sourceUpdatedAt'));

        return new ErpMaintenanceContractData($id, $customer, $serial, $starts, $ends, $optional($value('printerModel')), $optional($value('contractReference')), $source !== null ? new \DateTimeImmutable($source) : null);
    }

    private function date(mixed $value): \DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \UnexpectedValueException('Required ERP date is missing.');
        }

        return new \DateTimeImmutable($value);
    }

    private function requiredString(mixed $value): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            throw new \UnexpectedValueException('Required ERP field is missing.');
        }

        return trim((string) $value);
    }
}
