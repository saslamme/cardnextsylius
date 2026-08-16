<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product\Product;
use App\Entity\Product\ProductAttributeValue;
use Doctrine\ORM\EntityManagerInterface;

final class OpenAiProductAttributeEnricher
{
    private const HIDDEN_CODES = ['CN_MPN', 'CN_EAN'];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{
     *     products_scanned:int,
     *     api_calls:int,
     *     products_changed:int,
     *     candidate_slots:int,
     *     suggestions_received:int,
     *     values_written:int,
     *     values_would_write:int,
     *     low_confidence_skipped:int,
     *     invalid_values_skipped:int,
     *     unknown_attribute_skipped:int,
     *     products_without_empty_slots:int,
     *     api_errors:int,
     *     changes:list<array{product:string,name:string,attribute:string,old:mixed,new:mixed,confidence:float,evidence:string}>,
     *     errors:list<string>
     * }
     */
    public function enrich(
        bool $dryRun = false,
        bool $overwrite = false,
        ?string $onlyProductCode = null,
        ?int $limit = null,
        float $minimumConfidence = 0.90,
        bool $webSearch = false,
    ): array {
        $apiKey = $this->env('OPENAI_API_KEY');
        if ($apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $model = $this->env('OPENAI_ATTRIBUTE_MODEL');
        if ($model === '') {
            $model = 'gpt-5-mini';
        }

        $minimumConfidence = max(0.0, min(1.0, $minimumConfidence));

        $result = [
            'products_scanned' => 0,
            'api_calls' => 0,
            'products_changed' => 0,
            'candidate_slots' => 0,
            'suggestions_received' => 0,
            'values_written' => 0,
            'values_would_write' => 0,
            'low_confidence_skipped' => 0,
            'invalid_values_skipped' => 0,
            'unknown_attribute_skipped' => 0,
            'products_without_empty_slots' => 0,
            'api_errors' => 0,
            'changes' => [],
            'errors' => [],
        ];

        $products = $this->loadProducts($onlyProductCode, $limit);

        foreach ($products as $product) {
            ++$result['products_scanned'];

            $slots = $this->candidateSlots($product, $overwrite);
            $result['candidate_slots'] += count($slots);

            if ($slots === []) {
                ++$result['products_without_empty_slots'];
                continue;
            }

            try {
                ++$result['api_calls'];
                $suggestions = $this->requestSuggestions($product, $slots, $apiKey, $model, $webSearch);
            } catch (\Throwable $exception) {
                ++$result['api_errors'];
                if (count($result['errors']) < 50) {
                    $result['errors'][] = sprintf('%s: %s', (string) $product->getCode(), $exception->getMessage());
                }
                continue;
            }

            $productChanged = false;

            foreach ($suggestions as $suggestion) {
                ++$result['suggestions_received'];

                $code = isset($suggestion['code']) && is_string($suggestion['code']) ? $suggestion['code'] : '';
                if ($code === '' || !isset($slots[$code])) {
                    ++$result['unknown_attribute_skipped'];
                    continue;
                }

                $confidence = isset($suggestion['confidence']) && is_numeric($suggestion['confidence'])
                    ? (float) $suggestion['confidence']
                    : 0.0;
                if ($confidence < $minimumConfidence) {
                    ++$result['low_confidence_skipped'];
                    continue;
                }

                $values = isset($suggestion['values']) && is_array($suggestion['values'])
                    ? $suggestion['values']
                    : [];

                $normalized = $this->normalizeForSlot($slots[$code], $values);
                if (!$this->hasValue($normalized)) {
                    ++$result['invalid_values_skipped'];
                    continue;
                }

                $current = $slots[$code]->getValue();
                if ($this->sameValue($current, $normalized)) {
                    continue;
                }

                ++$result['values_would_write'];
                $productChanged = true;

                if (count($result['changes']) < 150) {
                    $result['changes'][] = [
                        'product' => (string) $product->getCode(),
                        'name' => $this->productName($product),
                        'attribute' => $code,
                        'old' => $current,
                        'new' => $normalized,
                        'confidence' => $confidence,
                        'evidence' => isset($suggestion['evidence']) && is_string($suggestion['evidence'])
                            ? trim($suggestion['evidence'])
                            : '',
                    ];
                }

                if (!$dryRun) {
                    $slots[$code]->setValue($normalized);
                    ++$result['values_written'];
                }
            }

            if ($productChanged) {
                ++$result['products_changed'];
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }

    /** @return list<Product> */
    private function loadProducts(?string $onlyProductCode, ?int $limit): array
    {
        $repository = $this->entityManager->getRepository(Product::class);

        if ($onlyProductCode !== null && trim($onlyProductCode) !== '') {
            $product = $repository->findOneBy(['code' => trim($onlyProductCode)]);
            return $product instanceof Product ? [$product] : [];
        }

        /** @var list<Product> $products */
        $products = $repository->findBy([], ['id' => 'ASC'], $limit !== null && $limit > 0 ? $limit : null);
        return $products;
    }

    /** @return array<string, ProductAttributeValue> */
    private function candidateSlots(Product $product, bool $overwrite): array
    {
        $slots = [];

        foreach ($product->getAttributes() as $attributeValue) {
            if (!$attributeValue instanceof ProductAttributeValue) {
                continue;
            }

            $code = (string) $attributeValue->getCode();
            if ($code === '' || in_array($code, self::HIDDEN_CODES, true)) {
                continue;
            }

            if (!$overwrite && $this->hasValue($attributeValue->getValue())) {
                continue;
            }

            $slots[$code] = $attributeValue;
        }

        return $slots;
    }

    /**
     * @param array<string, ProductAttributeValue> $slots
     * @return list<array{code:string,values:list<string>,confidence:float,evidence:string}>
     */
    private function requestSuggestions(
        Product $product,
        array $slots,
        string $apiKey,
        string $model,
        bool $webSearch,
    ): array {
        $attributeDefinitions = [];
        foreach ($slots as $code => $slot) {
            $attribute = $slot->getAttribute();
            if ($attribute === null) {
                continue;
            }

            $choices = [];
            foreach ((array) ($attribute->getConfiguration()['choices'] ?? []) as $choice => $labels) {
                $label = is_array($labels)
                    ? (string) ($labels['de_DE'] ?? $labels['en_US'] ?? reset($labels) ?: $choice)
                    : (string) $labels;
                $choices[(string) $choice] = $label;
            }

            $attributeDefinitions[] = [
                'code' => $code,
                'name' => (string) $attribute->getName(),
                'storage' => (string) $attribute->getStorageType(),
                'multiple' => (bool) ($attribute->getConfiguration()['multiple'] ?? false),
                'allowed_choices' => $choices,
            ];
        }

        $facts = $this->productFacts($product);
        $system = <<<'TXT'
You extract ecommerce product specifications. Fill only attributes that are supported by reliable evidence.
Rules:
- Never invent a value.
- Prefer exact statements from the supplied product text.
- If web search is available, use it only for the exact manufacturer/model/MPN and prefer official manufacturer sources.
- For select attributes, return only the exact allowed choice keys supplied in allowed_choices.
- For integer/float attributes, return only the numeric value without units.
- For boolean attributes, return exactly "true" or "false".
- For text attributes, return a short normalized factual value.
- Omit an attribute when evidence is missing, ambiguous, model-dependent, optional, or only generally true for a product family.
- Confidence must reflect evidence quality. Use >= 0.95 for explicit exact-source facts, 0.90-0.94 for very strong unambiguous evidence, below 0.90 otherwise.
- Evidence must be a short description of the supporting statement/source, not a long quote.
TXT;

        $user = "PRODUCT\n".json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ."\n\nEMPTY ATTRIBUTES TO FILL\n".json_encode($attributeDefinitions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $payload = [
            'model' => $model,
            'store' => false,
            'input' => [
                ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $system]]],
                ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $user]]],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'cardnext_product_attributes',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'attributes' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'code' => ['type' => 'string'],
                                        'values' => ['type' => 'array', 'items' => ['type' => 'string']],
                                        'confidence' => ['type' => 'number'],
                                        'evidence' => ['type' => 'string'],
                                    ],
                                    'required' => ['code', 'values', 'confidence', 'evidence'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['attributes'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];

        if ($webSearch) {
            $payload['tools'] = [['type' => 'web_search']];
        }

        $response = $this->postJson('https://api.openai.com/v1/responses', $payload, $apiKey);
        $text = $this->responseOutputText($response);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !isset($decoded['attributes']) || !is_array($decoded['attributes'])) {
            throw new \RuntimeException('OpenAI returned an unexpected attribute payload.');
        }

        $result = [];
        foreach ($decoded['attributes'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $result[] = [
                'code' => isset($item['code']) ? (string) $item['code'] : '',
                'values' => isset($item['values']) && is_array($item['values'])
                    ? array_values(array_map('strval', $item['values']))
                    : [],
                'confidence' => isset($item['confidence']) && is_numeric($item['confidence']) ? (float) $item['confidence'] : 0.0,
                'evidence' => isset($item['evidence']) ? (string) $item['evidence'] : '',
            ];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function productFacts(Product $product): array
    {
        $translation = null;
        foreach ($product->getTranslations() as $candidate) {
            if (method_exists($candidate, 'getLocale') && $candidate->getLocale() === 'de_DE') {
                $translation = $candidate;
                break;
            }
            $translation ??= $candidate;
        }

        $variants = [];
        foreach ($product->getVariants() as $variant) {
            $variants[] = [
                'code' => (string) $variant->getCode(),
                'mpn' => method_exists($variant, 'getManufacturerPartNumber') ? $variant->getManufacturerPartNumber() : null,
                'gtin' => method_exists($variant, 'getGtin') ? $variant->getGtin() : null,
            ];
            if (count($variants) >= 10) {
                break;
            }
        }

        $description = $translation !== null && method_exists($translation, 'getDescription')
            ? (string) ($translation->getDescription() ?? '')
            : '';
        $description = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $description) ?? $description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $description = preg_replace('/[ \t]+/', ' ', $description) ?? $description;
        $description = preg_replace('/\n{3,}/', "\n\n", $description) ?? $description;
        if (mb_strlen($description) > 20000) {
            $description = mb_substr($description, 0, 20000);
        }

        return [
            'product_code' => (string) $product->getCode(),
            'name' => $this->productName($product),
            'manufacturer' => $product->getManufacturer()?->getName(),
            'model' => $product->getModel(),
            'variants' => $variants,
            'description' => $description,
        ];
    }

    private function productName(Product $product): string
    {
        foreach ($product->getTranslations() as $translation) {
            if (method_exists($translation, 'getLocale') && $translation->getLocale() === 'de_DE' && method_exists($translation, 'getName')) {
                return (string) ($translation->getName() ?? $product->getCode());
            }
        }

        return (string) ($product->getName() ?? $product->getCode());
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function postJson(string $url, array $payload, string $apiKey): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                throw new \RuntimeException('Could not initialize cURL.');
            }

            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer '.$apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 120,
            ]);

            $raw = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($raw === false) {
                throw new \RuntimeException('OpenAI request failed: '.$error);
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Authorization: Bearer {$apiKey}\r\nContent-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => 120,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = file_get_contents($url, false, $context);
            if ($raw === false) {
                throw new \RuntimeException('OpenAI request failed.');
            }
            $status = 0;
            foreach ($http_response_header ?? [] as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $match)) {
                    $status = (int) $match[1];
                }
            }
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenAI returned invalid JSON.');
        }

        if ($status < 200 || $status >= 300) {
            $message = isset($decoded['error']['message']) ? (string) $decoded['error']['message'] : ('HTTP '.$status);
            throw new \RuntimeException('OpenAI API error: '.$message);
        }

        return $decoded;
    }

    /** @param array<string,mixed> $response */
    private function responseOutputText(array $response): string
    {
        foreach (($response['output'] ?? []) as $output) {
            if (!is_array($output) || ($output['type'] ?? null) !== 'message') {
                continue;
            }
            foreach (($output['content'] ?? []) as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return trim((string) $content['text']);
                }
            }
        }

        return '';
    }

    private function normalizeForSlot(ProductAttributeValue $slot, array $values): mixed
    {
        $attribute = $slot->getAttribute();
        if ($attribute === null) {
            return null;
        }

        $values = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $values,
        ), static fn (string $value): bool => $value !== '')));

        if ($values === []) {
            return null;
        }

        return match ($attribute->getStorageType()) {
            'boolean' => filter_var($values[0], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'integer' => preg_match('/^-?\d+$/', $values[0]) ? (int) $values[0] : null,
            'float' => is_numeric(str_replace(',', '.', $values[0])) ? (float) str_replace(',', '.', $values[0]) : null,
            'json' => $this->normalizeSelectValues($values, (array) ($attribute->getConfiguration()['choices'] ?? []), (bool) ($attribute->getConfiguration()['multiple'] ?? false)),
            default => trim(implode(', ', $values)) ?: null,
        };
    }

    /** @param list<string> $values @param array<string,mixed> $choices */
    private function normalizeSelectValues(array $values, array $choices, bool $multiple): ?array
    {
        $allowed = array_keys($choices);
        $values = array_values(array_intersect($values, $allowed));
        if ($values === []) {
            return null;
        }
        if (!$multiple) {
            return [$values[0]];
        }
        return $values;
    }

    private function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }
        return true;
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        if (is_array($left) && is_array($right)) {
            $left = array_values(array_map('strval', $left));
            $right = array_values(array_map('strval', $right));
            sort($left);
            sort($right);
            return $left === $right;
        }
        return $left === $right;
    }

    private function env(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        return is_string($value) ? trim($value) : '';
    }
}
