<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Taxonomy\Taxon;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Computes all visible values from a bounded product-id set. The query count is
 * constant (candidate ids, attribute rows and manufacturer groups), never one
 * query per product or facet value.
 */
final readonly class ProductFacetService
{
    private const MAX_DISCRETE_VALUES = 50;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductAttributeProfileService $profiles,
    ) {
    }

    /**
     * @return array{manufacturer: array<string, array{label: string, count: int}>, attributes: array<string, array<string, int>>}
     */
    public function getFacets(Taxon $taxon, ChannelInterface $channel, Request $request, string $profileCode): array
    {
        $definitions = $this->profiles->getFilterableDefinitionsForProfile($profileCode, $request->getLocale());
        if ($definitions === []) {
            return ['manufacturer' => [], 'attributes' => []];
        }

        [$manufacturerCodes, $attributeValues] = $this->sanitizeCriteria($request, $definitions);
        $connection = $this->entityManager->getConnection();
        $sql = <<<'SQL'
SELECT DISTINCT p.id
FROM sylius_product p
INNER JOIN sylius_product_channels pc ON pc.product_id = p.id AND pc.channel_id = :channel
INNER JOIN sylius_product_taxon pt ON pt.product_id = p.id
INNER JOIN sylius_taxon assigned ON assigned.id = pt.taxon_id
WHERE p.enabled = 1
  AND assigned.tree_root = :treeRoot
  AND assigned.tree_left >= :taxonLeft
  AND assigned.tree_right <= :taxonRight
SQL;
        $parameters = [
            'channel' => $channel->getId(),
            'treeRoot' => $taxon->getRoot()?->getId() ?? $taxon->getId(),
            'taxonLeft' => $taxon->getLeft(),
            'taxonRight' => $taxon->getRight(),
        ];
        $types = [];

        if ($manufacturerCodes !== []) {
            $sql .= ' AND EXISTS (SELECT 1 FROM cardnext_manufacturer m WHERE m.id = p.manufacturer_id AND m.code IN (:manufacturers))';
            $parameters['manufacturers'] = $manufacturerCodes;
            $types['manufacturers'] = ArrayParameterType::STRING;
        }

        $index = 0;
        foreach ($attributeValues as $code => $values) {
            $alias = 'fav' . $index;
            $sql .= sprintf(' AND EXISTS (SELECT 1 FROM sylius_product_attribute_value %s INNER JOIN sylius_product_attribute fa%d ON fa%d.id = %s.attribute_id WHERE %s.product_id = p.id AND fa%d.code = :code%d AND (', $alias, $index, $index, $alias, $alias, $index, $index);
            $parts = [];
            foreach ($values as $valueIndex => $value) {
                $parameter = sprintf('value%d_%d', $index, $valueIndex);
                $parts[] = sprintf('%s.json_value LIKE :%s', $alias, $parameter);
                $parameters[$parameter] = '%"' . $value . '"%';
            }
            $sql .= implode(' OR ', $parts) . '))';
            $parameters['code' . $index] = $code;
            ++$index;
        }

        // @phpstan-ignore argument.type
        $productIds = array_map('intval', $connection->fetchFirstColumn($sql, $parameters, $types));
        if ($productIds === []) {
            return ['manufacturer' => [], 'attributes' => []];
        }

        $manufacturerRows = $connection->fetchAllAssociative(
            'SELECT m.code, m.name, COUNT(DISTINCT p.id) amount FROM sylius_product p INNER JOIN cardnext_manufacturer m ON m.id = p.manufacturer_id AND m.enabled = 1 WHERE p.id IN (:ids) GROUP BY m.id, m.code, m.name ORDER BY m.position, m.name',
            ['ids' => $productIds],
            ['ids' => ArrayParameterType::INTEGER],
        );
        $manufacturers = [];
        foreach ($manufacturerRows as $row) {
            $manufacturers[(string) $row['code']] = ['label' => (string) $row['name'], 'count' => (int) $row['amount']]; // @phpstan-ignore-line
        }

        $rows = $connection->fetchAllAssociative(
            'SELECT pav.product_id, pa.code, pav.json_value, pav.boolean_value, pav.integer_value, pav.float_value, pav.text_value FROM sylius_product_attribute_value pav INNER JOIN sylius_product_attribute pa ON pa.id = pav.attribute_id WHERE pav.product_id IN (:ids) AND pa.code IN (:codes)',
            ['ids' => $productIds, 'codes' => array_keys($definitions)],
            ['ids' => ArrayParameterType::INTEGER, 'codes' => ArrayParameterType::STRING],
        );
        $counts = [];
        $seen = [];
        foreach ($rows as $row) {
            // @phpstan-ignore cast.string
            $code = (string) $row['code'];
            $values = $this->storedValues($row);
            foreach ($values as $value) {
                // @phpstan-ignore cast.int
                if (!isset($definitions[$code]['choices'][$value]) || isset($seen[$code][$value][(int) $row['product_id']])) {
                    continue;
                }
                // @phpstan-ignore cast.int
                $seen[$code][$value][(int) $row['product_id']] = true;
                $counts[$code][$value] = ($counts[$code][$value] ?? 0) + 1;
            }
        }

        foreach ($counts as $code => $values) {
            if (count($values) > self::MAX_DISCRETE_VALUES) {
                unset($counts[$code]);
            }
        }

        return ['manufacturer' => $manufacturers, 'attributes' => $counts];
    }

    /** @param array<string, array<string, mixed>> $definitions */
    // @phpstan-ignore missingType.iterableValue
    private function sanitizeCriteria(Request $request, array $definitions): array
    {
        $criteria = $request->query->all('criteria');
        // @phpstan-ignore offsetAccess.nonOffsetAccessible
        $manufacturer = $this->scalarList($criteria['manufacturer']['value'] ?? $criteria['manufacturer'] ?? []);
        $attributes = [];
        foreach ($definitions as $code => $definition) {
            $name = strtolower($code);
            // @phpstan-ignore offsetAccess.nonOffsetAccessible
            $submitted = $this->scalarList($criteria[$name]['value'] ?? $criteria[$name] ?? []);
            // @phpstan-ignore argument.type
            $valid = array_values(array_intersect($submitted, array_keys($definition['choices'])));
            if ($valid !== []) {
                $attributes[$code] = $valid;
            }
        }

        return [$manufacturer, $attributes];
    }

    /** @return list<string> */
    private function scalarList(mixed $value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        return array_values(array_unique(array_map('strval', array_filter($value, static fn (mixed $item): bool => is_scalar($item) && (string) $item !== ''))));
    }

    /** @param array<string, mixed> $row @return list<string> */
    // @phpstan-ignore missingType.iterableValue
    private function storedValues(array $row): array
    {
        if (is_string($row['json_value']) && !in_array($row['json_value'], ['', '[]', '{}', 'null'], true)) {
            $decoded = json_decode($row['json_value'], true);
            if (is_scalar($decoded)) {
                return [(string) $decoded];
            }
            if (is_array($decoded)) {
                return $this->scalarList($decoded);
            }
        }
        if ($row['boolean_value'] !== null) {
            return [(bool) $row['boolean_value'] ? '1' : '0'];
        }

        foreach (['integer_value', 'float_value', 'text_value'] as $column) {
            // @phpstan-ignore cast.string
            if ($row[$column] !== null && trim((string) $row[$column]) !== '') {
                // @phpstan-ignore cast.string
                return [(string) $row[$column]];
            }
        }

        return [];
    }
}
