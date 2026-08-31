<?php

declare(strict_types=1);

namespace App\Service\Search;

use Doctrine\DBAL\Connection;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final class CardnextProductSearch
{
    /**
     * @var list<array{key: string, terms: list<string>}>
     */
    private array $choiceSearchEntries = [];

    private ?string $choiceSearchLocale = null;

    /**
     * @var array<string, string>
     */
    private array $fuzzyVocabulary = [];

    private ?string $fuzzyVocabularyLocale = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    // @phpstan-ignore missingType.iterableValue
    public function search(string $query, string $localeCode, int $limit = 48, int $offset = 0): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $originalQuery = $this->cleanQuery($query);
        $result = $this->strictSearch($originalQuery, $localeCode, $limit, $offset);

        $result['correctedQuery'] = null;
        $result['fuzzy'] = false;

        if ($result['total'] > 0 || mb_strlen($originalQuery) < 4) {
            return $result;
        }

        $correctedQuery = $this->correctQuery($originalQuery, $localeCode);

        if (
            $correctedQuery === $originalQuery
            || mb_strtolower($correctedQuery) === mb_strtolower($originalQuery)
        ) {
            return $result;
        }

        $corrected = $this->strictSearch($correctedQuery, $localeCode, $limit, $offset);

        if ($corrected['total'] === 0) {
            return $result;
        }

        $corrected['query'] = $originalQuery;
        $corrected['correctedQuery'] = $correctedQuery;
        $corrected['fuzzy'] = true;

        return $corrected;
    }

    // @phpstan-ignore missingType.iterableValue
    private function strictSearch(string $query, string $localeCode, int $limit = 48, int $offset = 0): array
    {
        $query = $this->cleanQuery($query);

        if ($query === '') {
            return ['query' => '', 'total' => 0, 'products' => []];
        }

        $channelId = $this->channelContext->getChannel()->getId();

        if ($channelId === null) {
            return ['query' => $query, 'total' => 0, 'products' => []];
        }

        $lowerQuery = mb_strtolower($query);
        $normalizedQuery = $this->normalizeIdentifier($query);
        $tokens = $this->tokens($query);

        $parameters = [
            'channelId' => $channelId,
            'localeCode' => $localeCode,
            'query' => $lowerQuery,
            'contains' => '%' . $lowerQuery . '%',
            'prefix' => $lowerQuery . '%',
            'normalizedQuery' => $normalizedQuery,
            'normalizedPrefix' => $normalizedQuery . '%',
            'normalizedContains' => '%' . $normalizedQuery . '%',
        ];

        $tokenConditions = [];

        foreach ($tokens as $index => $token) {
            $rawName = 'token_' . $index;
            $normalizedName = 'token_norm_' . $index;

            $parameters[$rawName] = '%' . mb_strtolower($token) . '%';
            $parameters[$normalizedName] = '%' . $this->normalizeIdentifier($token) . '%';

            $technicalCondition = $this->buildTechnicalAttributeCondition(
                $token,
                $index,
                $localeCode,
                $parameters,
            );

            $tokenConditions[] = sprintf(
                "(LOWER(pt.name) LIKE :%1\$s
                    OR LOWER(p.code) LIKE :%1\$s
                    OR LOWER(COALESCE(pv.code, '')) LIKE :%1\$s
                    OR LOWER(COALESCE(m.name, '')) LIKE :%1\$s
                    OR LOWER(COALESCE(m.code, '')) LIKE :%1\$s
                    OR LOWER(COALESCE(pv.manufacturer_part_number, '')) LIKE :%1\$s
                    OR LOWER(COALESCE(pv.gtin, '')) LIKE :%1\$s
                    OR COALESCE(pv.manufacturer_part_number_normalized, '') LIKE :%2\$s
                    OR COALESCE(pv.gtin_normalized, '') LIKE :%2\$s
                    OR %3\$s)",
                $rawName,
                $normalizedName,
                $technicalCondition,
            );
        }

        $whereSearch = $tokenConditions !== []
            ? implode(' AND ', $tokenConditions)
            : '1 = 0';

        $productCodeNormalized = $this->sqlNormalized('p.code');
        $variantCodeNormalized = $this->sqlNormalized('pv.code');

        $baseFrom = "
            FROM sylius_product p
            INNER JOIN sylius_product_translation pt
                ON pt.translatable_id = p.id
                AND pt.locale = :localeCode
            INNER JOIN sylius_product_channels pc
                ON pc.product_id = p.id
                AND pc.channel_id = :channelId
            LEFT JOIN sylius_product_variant pv
                ON pv.product_id = p.id
                AND pv.enabled = 1
            LEFT JOIN cardnext_manufacturer m
                ON m.id = p.manufacturer_id
            WHERE p.enabled = 1
              AND ({$whereSearch})
        ";

        // @phpstan-ignore cast.int
        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(DISTINCT p.id) {$baseFrom}",
            $parameters,
        );

        $limit = max(1, min(100, $limit));

        $score = "
            MAX(
                CASE
                    WHEN COALESCE(pv.gtin_normalized, '') = :normalizedQuery
                        AND :normalizedQuery <> '' THEN 160

                    WHEN COALESCE(pv.manufacturer_part_number_normalized, '') = :normalizedQuery
                        AND :normalizedQuery <> '' THEN 150

                    WHEN LOWER(p.code) = :query THEN 130

                    WHEN {$productCodeNormalized} = :normalizedQuery
                        AND :normalizedQuery <> '' THEN 128

                    WHEN LOWER(COALESCE(pv.code, '')) = :query THEN 126

                    WHEN {$variantCodeNormalized} = :normalizedQuery
                        AND :normalizedQuery <> '' THEN 124

                    WHEN LOWER(pt.name) = :query THEN 110
                    WHEN LOWER(COALESCE(m.name, '')) = :query THEN 105
                    WHEN LOWER(COALESCE(m.code, '')) = :query THEN 103

                    WHEN COALESCE(pv.manufacturer_part_number_normalized, '') LIKE :normalizedPrefix
                        AND :normalizedQuery <> '' THEN 102

                    WHEN COALESCE(pv.gtin_normalized, '') LIKE :normalizedPrefix
                        AND :normalizedQuery <> '' THEN 101

                    WHEN LOWER(p.code) LIKE :prefix THEN 96
                    WHEN LOWER(COALESCE(pv.code, '')) LIKE :prefix THEN 94

                    WHEN {$productCodeNormalized} LIKE :normalizedPrefix
                        AND :normalizedQuery <> '' THEN 92

                    WHEN {$variantCodeNormalized} LIKE :normalizedPrefix
                        AND :normalizedQuery <> '' THEN 90

                    WHEN LOWER(pt.name) LIKE :prefix THEN 86
                    WHEN LOWER(COALESCE(m.name, '')) LIKE :prefix THEN 80

                    WHEN COALESCE(pv.manufacturer_part_number_normalized, '') LIKE :normalizedContains
                        AND :normalizedQuery <> '' THEN 79

                    WHEN COALESCE(pv.gtin_normalized, '') LIKE :normalizedContains
                        AND :normalizedQuery <> '' THEN 78

                    WHEN LOWER(p.code) LIKE :contains THEN 74
                    WHEN LOWER(COALESCE(pv.code, '')) LIKE :contains THEN 72
                    WHEN LOWER(pt.name) LIKE :contains THEN 66
                    WHEN LOWER(COALESCE(m.name, '')) LIKE :contains THEN 58

                    ELSE 40
                END
            )
        ";

        // Select all display fields from the same, deterministically ranked variant.
        // Identifier matches win; otherwise the lowest-ID enabled variant is used.
        $displayVariantOrder = "
            CASE
                WHEN COALESCE(display_pv.gtin_normalized, '') = :normalizedQuery
                    AND :normalizedQuery <> '' THEN 60
                WHEN COALESCE(display_pv.manufacturer_part_number_normalized, '') = :normalizedQuery
                    AND :normalizedQuery <> '' THEN 50
                WHEN COALESCE(display_pv.gtin_normalized, '') LIKE :normalizedPrefix
                    AND :normalizedQuery <> '' THEN 40
                WHEN COALESCE(display_pv.manufacturer_part_number_normalized, '') LIKE :normalizedPrefix
                    AND :normalizedQuery <> '' THEN 39
                WHEN COALESCE(display_pv.gtin_normalized, '') LIKE :normalizedContains
                    AND :normalizedQuery <> '' THEN 30
                WHEN COALESCE(display_pv.manufacturer_part_number_normalized, '') LIKE :normalizedContains
                    AND :normalizedQuery <> '' THEN 29
                WHEN {$this->sqlNormalized('display_pv.code')} = :normalizedQuery
                    AND :normalizedQuery <> '' THEN 20
                ELSE 0
            END DESC,
            display_pv.id ASC
        ";

        $variantValue = static fn (string $column): string => "
            (
                SELECT display_pv.{$column}
                FROM sylius_product_variant display_pv
                WHERE display_pv.product_id = p.id
                  AND display_pv.enabled = 1
                ORDER BY {$displayVariantOrder}
                LIMIT 1
            )
        ";

        $sql = "
            SELECT
                p.id,
                p.code,
                {$variantValue('code')} AS variant_code,
                {$variantValue('manufacturer_part_number')} AS manufacturer_part_number,
                {$variantValue('gtin')} AS gtin,
                pt.name,
                pt.slug,
                m.name AS manufacturer,
                m.code AS manufacturer_code,
                {$score} AS search_score
            {$baseFrom}
            GROUP BY
                p.id,
                p.code,
                pt.name,
                pt.slug,
                m.name,
                m.code
            ORDER BY search_score DESC, pt.name ASC
            LIMIT {$limit}
            OFFSET {$offset}
        ";

        $rows = $this->connection->fetchAllAssociative($sql, $parameters);

        $products = array_map(
            static fn (array $row): array => [
                // @phpstan-ignore cast.int
                'id' => (int) $row['id'],
                // @phpstan-ignore cast.string
                'code' => (string) $row['code'],
                'manufacturerPartNumber' => $row['manufacturer_part_number'] !== null
                    // @phpstan-ignore cast.string
                    ? (string) $row['manufacturer_part_number']
                    : null,
                // @phpstan-ignore cast.string
                'gtin' => $row['gtin'] !== null ? (string) $row['gtin'] : null,
                // @phpstan-ignore cast.string
                'variantCode' => $row['variant_code'] !== null ? (string) $row['variant_code'] : null,
                // @phpstan-ignore cast.string
                'name' => (string) $row['name'],
                // @phpstan-ignore cast.string
                'slug' => (string) $row['slug'],
                'manufacturer' => $row['manufacturer'] !== null
                    // @phpstan-ignore cast.string
                    ? (string) $row['manufacturer']
                    : null,
                'manufacturerCode' => $row['manufacturer_code'] !== null
                    // @phpstan-ignore cast.string
                    ? (string) $row['manufacturer_code']
                    : null,
                // @phpstan-ignore cast.int
                'score' => (int) $row['search_score'],
            ],
            $rows,
        );

        return [
            'query' => $query,
            'total' => $total,
            'products' => $products,
        ];
    }

    /**
     * Returns one unique product when the query exactly matches a product
     * identifier. Product names and manufacturers deliberately do not trigger
     * redirects.
     *
     * @return array{id: int, slug: string}|null
     */
    public function findExactProductByIdentifier(string $query, string $localeCode): ?array
    {
        $query = $this->cleanQuery($query);

        if (mb_strlen($query) < 3) {
            return null;
        }

        $channelId = $this->channelContext->getChannel()->getId();

        if ($channelId === null) {
            return null;
        }

        $normalizedQuery = $this->normalizeIdentifier($query);

        if ($normalizedQuery === '') {
            return null;
        }

        $productCodeNormalized = $this->sqlNormalized('p.code');
        $variantCodeNormalized = $this->sqlNormalized('pv.code');

        $sql = "
            SELECT DISTINCT
                p.id,
                pt.slug
            FROM sylius_product p
            INNER JOIN sylius_product_translation pt
                ON pt.translatable_id = p.id
                AND pt.locale = :localeCode
            INNER JOIN sylius_product_channels pc
                ON pc.product_id = p.id
                AND pc.channel_id = :channelId
            LEFT JOIN sylius_product_variant pv
                ON pv.product_id = p.id
                AND pv.enabled = 1
            WHERE p.enabled = 1
              AND (
                  COALESCE(pv.gtin_normalized, '') = :normalizedQuery
                  OR COALESCE(pv.manufacturer_part_number_normalized, '') = :normalizedQuery
                  OR {$productCodeNormalized} = :normalizedQuery
                  OR {$variantCodeNormalized} = :normalizedQuery
                  OR EXISTS (
                      SELECT 1
                      FROM sylius_product_attribute_value pav_exact
                      INNER JOIN sylius_product_attribute pa_exact
                          ON pa_exact.id = pav_exact.attribute_id
                      WHERE pav_exact.product_id = p.id
                        AND pa_exact.code IN ('CN_MPN', 'CN_EAN')
                        AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(pav_exact.text_value, '')), '-', ''), ' ', ''), '_', ''), '.', ''), '/', '') = :normalizedQuery
                  )
              )
            LIMIT 2
        ";

        $rows = $this->connection->fetchAllAssociative($sql, [
            'channelId' => $channelId,
            'localeCode' => $localeCode,
            'normalizedQuery' => $normalizedQuery,
        ]);

        if (count($rows) !== 1) {
            return null;
        }

        return [
            // @phpstan-ignore cast.int
            'id' => (int) $rows[0]['id'],
            // @phpstan-ignore cast.string
            'slug' => (string) $rows[0]['slug'],
        ];
    }

    // @phpstan-ignore missingType.iterableValue
    public function manufacturers(string $query, int $limit = 4): array
    {
        $query = $this->cleanQuery($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $channelId = $this->channelContext->getChannel()->getId();

        if ($channelId === null) {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $lowerQuery = mb_strtolower($query);

        $sql = "
            SELECT DISTINCT m.name, m.code
            FROM cardnext_manufacturer m
            INNER JOIN sylius_product p
                ON p.manufacturer_id = m.id
                AND p.enabled = 1
            INNER JOIN sylius_product_channels pc
                ON pc.product_id = p.id
                AND pc.channel_id = :channelId
            WHERE m.enabled = 1
              AND (
                  LOWER(m.name) LIKE :contains
                  OR LOWER(m.code) LIKE :contains
              )
            ORDER BY
                CASE
                    WHEN LOWER(m.name) = :query THEN 0
                    WHEN LOWER(m.code) = :query THEN 1
                    WHEN LOWER(m.name) LIKE :prefix THEN 2
                    ELSE 3
                END,
                m.name ASC
            LIMIT {$limit}
        ";

        return array_map(
            static fn (array $row): array => [
                // @phpstan-ignore cast.string
                'name' => (string) $row['name'],
                // @phpstan-ignore cast.string
                'code' => (string) $row['code'],
            ],
            $this->connection->fetchAllAssociative($sql, [
                'channelId' => $channelId,
                'query' => $lowerQuery,
                'prefix' => $lowerQuery . '%',
                'contains' => '%' . $lowerQuery . '%',
            ]),
        );
    }

    /**
     * Builds one correlated EXISTS condition for a single search token.
     *
     * A technical value counts only when the attribute actually has a value.
     * This prevents empty profile fields (or false checkboxes) from producing
     * irrelevant results just because their attribute code exists.
     *
     * @param array<string, mixed> $parameters
     */
    private function buildTechnicalAttributeCondition(
        string $token,
        int $index,
        string $localeCode,
        array &$parameters,
    ): string {
        $rawParam = 'attr_raw_' . $index;
        $normalizedParam = 'attr_norm_' . $index;

        $lowerToken = mb_strtolower($token);
        $normalizedToken = $this->normalizeSearchTerm($token);

        $parameters[$rawParam] = '%' . $lowerToken . '%';
        $parameters[$normalizedParam] = '%' . $normalizedToken . '%';

        $aliasConditions = [];

        foreach ($this->choiceAliasesForToken($token, $localeCode) as $aliasIndex => $alias) {
            $parameterName = sprintf('attr_alias_%d_%d', $index, $aliasIndex);
            $parameters[$parameterName] = '%' . mb_strtolower($alias) . '%';

            $aliasConditions[] = sprintf(
                "LOWER(CAST(pav.json_value AS CHAR)) LIKE :%s",
                $parameterName,
            );
        }

        $aliasSql = $aliasConditions !== []
            ? ' OR ' . implode(' OR ', $aliasConditions)
            : '';

        return "
            EXISTS (
                SELECT 1
                FROM sylius_product_attribute_value pav
                INNER JOIN sylius_product_attribute pa
                    ON pa.id = pav.attribute_id
                LEFT JOIN sylius_product_attribute_translation pat
                    ON pat.translatable_id = pa.id
                    AND pat.locale = :localeCode
                WHERE pav.product_id = p.id
                  AND (pav.locale_code IS NULL OR pav.locale_code = :localeCode)
                  AND (
                      NULLIF(TRIM(COALESCE(pav.text_value, '')), '') IS NOT NULL
                      OR pav.boolean_value = 1
                      OR pav.integer_value IS NOT NULL
                      OR pav.float_value IS NOT NULL
                      OR pav.datetime_value IS NOT NULL
                      OR pav.date_value IS NOT NULL
                      OR (
                          pav.json_value IS NOT NULL
                          AND CAST(pav.json_value AS CHAR) NOT IN ('null', '[]', '{}', '\"\"')
                      )
                  )
                  AND (
                      LOWER(COALESCE(pav.text_value, '')) LIKE :{$rawParam}
                      OR CAST(pav.integer_value AS CHAR) LIKE :{$rawParam}
                      OR CAST(pav.float_value AS CHAR) LIKE :{$rawParam}
                      OR CAST(pav.date_value AS CHAR) LIKE :{$rawParam}
                      OR CAST(pav.datetime_value AS CHAR) LIKE :{$rawParam}
                      OR LOWER(CAST(pav.json_value AS CHAR)) LIKE :{$rawParam}
                      OR LOWER(pa.code) LIKE :{$rawParam}
                      OR LOWER(COALESCE(pat.name, '')) LIKE :{$rawParam}
                      {$aliasSql}
                  )
            )
        ";
    }

    /**
     * @return list<string>
     */
    private function choiceAliasesForToken(string $token, string $localeCode): array
    {
        $needle = $this->normalizeSearchTerm($token);

        if (mb_strlen($needle) < 2) {
            return [];
        }

        $this->ensureChoiceSearchEntries($localeCode);

        $aliases = [];

        foreach ($this->choiceSearchEntries as $entry) {
            foreach ($entry['terms'] as $term) {
                if (
                    $term === $needle
                    || str_contains($term, $needle)
                    || (mb_strlen($term) >= 3 && str_contains($needle, $term))
                ) {
                    $aliases[$entry['key']] = true;
                    break;
                }
            }
        }

        return array_keys($aliases);
    }

    private function ensureChoiceSearchEntries(string $localeCode): void
    {
        if ($this->choiceSearchLocale === $localeCode) {
            return;
        }

        $this->choiceSearchLocale = $localeCode;
        $this->choiceSearchEntries = [];

        $rows = $this->connection->fetchAllAssociative(
            'SELECT code, configuration FROM sylius_product_attribute',
        );

        foreach ($rows as $row) {
            $configuration = $row['configuration'];

            if (is_string($configuration)) {
                $configuration = json_decode($configuration, true);
            }

            if (!is_array($configuration)) {
                continue;
            }

            $choices = $configuration['choices'] ?? null;

            if (!is_array($choices)) {
                continue;
            }

            foreach ($choices as $key => $labels) {
                // @phpstan-ignore function.alreadyNarrowedType, booleanAnd.alwaysFalse
                if (!is_string($key) && !is_int($key)) {
                    continue;
                }

                $key = (string) $key;
                $terms = [
                    $this->normalizeSearchTerm($key),
                ];

                if (is_array($labels)) {
                    $preferred = $labels[$localeCode] ?? null;

                    if (is_string($preferred)) {
                        $terms = array_merge(
                            $terms,
                            $this->searchTermsFromLabel($preferred),
                        );
                    }

                    foreach ($labels as $label) {
                        if (is_string($label)) {
                            $terms = array_merge(
                                $terms,
                                $this->searchTermsFromLabel($label),
                            );
                        }
                    }
                } elseif (is_string($labels)) {
                    $terms = array_merge(
                        $terms,
                        $this->searchTermsFromLabel($labels),
                    );
                }

                $terms = array_values(array_unique(array_filter(
                    $terms,
                    static fn (string $term): bool => mb_strlen($term) >= 2,
                )));

                if ($terms === []) {
                    continue;
                }

                $this->choiceSearchEntries[] = [
                    'key' => mb_strtolower($key),
                    'terms' => $terms,
                ];
            }
        }
    }

    /**
     * @return list<string>
     */
    private function searchTermsFromLabel(string $label): array
    {
        $terms = [
            $this->normalizeSearchTerm($label),
        ];

        $parts = preg_split('/[\s,;\/()|+\-–—]+/u', $label) ?: [];

        foreach ($parts as $part) {
            $normalized = $this->normalizeSearchTerm($part);

            if (mb_strlen($normalized) >= 2) {
                $terms[] = $normalized;
            }
        }

        return $terms;
    }

    private function correctQuery(string $query, string $localeCode): string
    {
        $this->ensureFuzzyVocabulary($localeCode);

        if ($this->fuzzyVocabulary === []) {
            return $query;
        }

        return preg_replace_callback(
            '/\p{L}{4,}/u',
            function (array $match): string {
                $word = $match[0];
                $normalized = $this->normalizeSearchTerm($word);

                if ($normalized === '' || isset($this->fuzzyVocabulary[$normalized])) {
                    return $word;
                }

                $candidate = $this->closestFuzzyCandidate($normalized);

                if ($candidate === null) {
                    return $word;
                }

                return $candidate;
            },
            $query,
        ) ?? $query;
    }

    private function closestFuzzyCandidate(string $needle): ?string
    {
        $length = mb_strlen($needle);

        if ($length < 4) {
            return null;
        }

        $maxDistance = match (true) {
            $length <= 4 => 1,
            $length <= 8 => 2,
            default => 3,
        };

        $firstCharacter = mb_substr($needle, 0, 1);
        $bestReplacement = null;
        $bestDistance = PHP_INT_MAX;
        $bestLengthDifference = PHP_INT_MAX;

        foreach ($this->fuzzyVocabulary as $normalizedCandidate => $replacement) {
            $candidateLength = mb_strlen($normalizedCandidate);
            $lengthDifference = abs($candidateLength - $length);

            if ($lengthDifference > $maxDistance) {
                continue;
            }

            if (mb_substr($normalizedCandidate, 0, 1) !== $firstCharacter) {
                continue;
            }

            $distance = $this->unicodeLevenshtein($needle, $normalizedCandidate);

            if ($distance > $maxDistance) {
                continue;
            }

            $ratio = $distance / max($length, $candidateLength);

            if ($ratio > 0.34) {
                continue;
            }

            if (
                $distance < $bestDistance
                || ($distance === $bestDistance && $lengthDifference < $bestLengthDifference)
            ) {
                $bestDistance = $distance;
                $bestLengthDifference = $lengthDifference;
                $bestReplacement = $replacement;
            }
        }

        return $bestReplacement;
    }

    private function ensureFuzzyVocabulary(string $localeCode): void
    {
        if ($this->fuzzyVocabularyLocale === $localeCode) {
            return;
        }

        $this->fuzzyVocabularyLocale = $localeCode;
        $this->fuzzyVocabulary = [];

        $channelId = $this->channelContext->getChannel()->getId();

        if ($channelId === null) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            "
                SELECT DISTINCT pt.name AS term
                FROM sylius_product p
                INNER JOIN sylius_product_translation pt
                    ON pt.translatable_id = p.id
                    AND pt.locale = :localeCode
                INNER JOIN sylius_product_channels pc
                    ON pc.product_id = p.id
                    AND pc.channel_id = :channelId
                WHERE p.enabled = 1

                UNION

                SELECT DISTINCT m.name AS term
                FROM cardnext_manufacturer m
                INNER JOIN sylius_product p
                    ON p.manufacturer_id = m.id
                    AND p.enabled = 1
                INNER JOIN sylius_product_channels pc
                    ON pc.product_id = p.id
                    AND pc.channel_id = :channelId
                WHERE m.enabled = 1

                UNION

                SELECT DISTINCT m.code AS term
                FROM cardnext_manufacturer m
                INNER JOIN sylius_product p
                    ON p.manufacturer_id = m.id
                    AND p.enabled = 1
                INNER JOIN sylius_product_channels pc
                    ON pc.product_id = p.id
                    AND pc.channel_id = :channelId
                WHERE m.enabled = 1

                UNION

                SELECT DISTINCT pat.name AS term
                FROM sylius_product_attribute_translation pat
                WHERE pat.locale = :localeCode
            ",
            [
                'channelId' => $channelId,
                'localeCode' => $localeCode,
            ],
        );

        foreach ($rows as $row) {
            // @phpstan-ignore cast.string
            $term = trim((string) ($row['term'] ?? ''));

            if ($term === '') {
                continue;
            }

            foreach ($this->fuzzyWordsFromText($term) as $word) {
                $this->addFuzzyVocabularyEntry($word);
            }
        }

        $this->ensureChoiceSearchEntries($localeCode);

        foreach ($this->choiceSearchEntries as $entry) {
            foreach ($entry['terms'] as $term) {
                $this->addFuzzyVocabularyEntry($term);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function fuzzyWordsFromText(string $text): array
    {
        preg_match_all('/\p{L}{4,}/u', mb_strtolower($text), $matches);

        // @phpstan-ignore nullCoalesce.offset
        return array_values(array_unique($matches[0] ?? []));
    }

    private function addFuzzyVocabularyEntry(string $word): void
    {
        $replacement = mb_strtolower(trim($word));
        $normalized = $this->normalizeSearchTerm($replacement);

        if (
            mb_strlen($normalized) < 4
            || preg_match('/^\p{L}+$/u', $replacement) !== 1
        ) {
            return;
        }

        $this->fuzzyVocabulary[$normalized] ??= $replacement;
    }

    private function unicodeLevenshtein(string $left, string $right): int
    {
        $a = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $aLength = count($a);
        $bLength = count($b);

        if ($aLength === 0) {
            return $bLength;
        }

        if ($bLength === 0) {
            return $aLength;
        }

        $previous = range(0, $bLength);

        for ($i = 1; $i <= $aLength; ++$i) {
            $current = [$i];

            for ($j = 1; $j <= $bLength; ++$j) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;

                $current[$j] = min(
                    $current[$j - 1] + 1,
                    $previous[$j] + 1,
                    $previous[$j - 1] + $cost,
                );
            }

            $previous = $current;
        }

        return $previous[$bLength];
    }

    private function cleanQuery(string $query): string
    {
        $query = preg_replace('/\s+/u', ' ', trim($query)) ?? '';

        return mb_substr($query, 0, 120);
    }

    private function normalizeIdentifier(string $value): string
    {
        $value = mb_strtolower($value);

        return preg_replace('/[^a-z0-9]+/i', '', $value) ?? '';
    }

    private function normalizeSearchTerm(string $value): string
    {
        $value = mb_strtolower($value);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }

    // @phpstan-ignore missingType.iterableValue
    private function tokens(string $query): array
    {
        $tokens = preg_split('/[\s,;\/]+/u', trim($query)) ?: [];
        $tokens = array_values(array_filter(
            array_map('trim', $tokens),
            static fn (string $token): bool => $token !== '',
        ));

        return array_slice($tokens, 0, 6);
    }

    private function sqlNormalized(string $field): string
    {
        return sprintf(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE(%s, '')), '-', ''), ' ', ''), '_', ''), '.', ''), '/', '')",
            $field,
        );
    }
}
