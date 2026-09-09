<?php

declare(strict_types=1);

namespace App\Integration\Erp\Maintenance;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpErpMaintenanceContractProvider implements ErpMaintenanceContractProviderInterface
{
    /** @param array<string, string> $fieldMap */
    public function __construct(private HttpClientInterface $httpClient, private LoggerInterface $logger, private string $url, private string $baseUri, private string $endpoint, private string $authHeader, private string $authValue, private array $fieldMap)
    {
    }

    public function fetchAll(): iterable
    {
        $url = trim($this->url) !== '' ? trim($this->url) : ($this->baseUri !== '' && $this->endpoint !== '' ? rtrim($this->baseUri, '/') . '/' . ltrim($this->endpoint, '/') : '');
        if ($url === '') {
            throw new \RuntimeException('ERP maintenance-contract URL is not configured.');
        }
        $headers = $this->authHeader !== '' && $this->authValue !== '' ? [$this->authHeader => $this->authValue] : [];
        $response = $this->httpClient->request('GET', $url, ['headers' => $headers, 'timeout' => 30, 'max_duration' => 35]);
        $content = $response->getContent();
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        try {
            $payload = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \UnexpectedValueException('Invalid JSON returned by the ERP maintenance-contract endpoint.', previous: $error);
        }
        if (!is_array($payload)) {
            throw new \UnexpectedValueException('ERP maintenance-contract response must contain a JSON array or object.');
        }

        $rows = $this->extractRows($payload);
        $normalizer = new IdbMasterMaintenanceContractNormalizer($this->fieldMap);
        foreach ($rows as $offset => $row) {
            try {
                if (!is_array($row)) {
                    throw new \UnexpectedValueException('Record is not an object.');
                }

                yield $normalizer->normalize($row);
            } catch (\Throwable $error) {
                $this->logger->warning('Invalid ERP maintenance contract skipped.', ['recordOffset' => $offset, 'reason' => $error->getMessage(), 'errorType' => $error::class]);
            }
        }
    }

    /**
     * @param array<mixed> $payload
     *
     * @return list<mixed>
     */
    private function extractRows(array $payload): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }
        foreach (['contracts', 'data', 'service_contracts', 'service_contract'] as $wrapper) {
            if (isset($payload[$wrapper]) && is_array($payload[$wrapper]) && array_is_list($payload[$wrapper])) {
                return $payload[$wrapper];
            }
        }

        throw new \UnexpectedValueException('Unsupported idbMaster maintenance-contract response structure.');
    }
}
