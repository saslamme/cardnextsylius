<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

/**
 * Supplies the production index names for ORM-managed many-to-many join tables.
 *
 * Doctrine has no attribute for indexes on a JoinTable. Without this schema-tool
 * hook it generates hash-based names (or omits an index covered by the primary
 * key), even though these indexes are intentionally present in production.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class ProductionIndexNameListener
{
    /** @var array<string, array<string, list<string>>> */
    private const INDEXES = [
        'cardnext_product_leasing_factor' => [
            'idx_leasing_product' => ['product_id'],
            'idx_leasing_factor' => ['leasing_factor_id'],
        ],
        'cardnext_cms_download_channel' => [
            'idx_cms_dc_download' => ['cms_download_id'],
            'idx_cms_dc_channel' => ['channel_id'],
        ],
        'cardnext_cms_download_product' => [
            'idx_cms_dp_download' => ['cms_download_id'],
            'idx_cms_dp_product' => ['product_id'],
        ],
        'cardnext_cms_page_channel' => [
            'idx_cms_pc_page' => ['cms_page_id'],
            'idx_cms_pc_channel' => ['channel_id'],
        ],
    ];

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $schema = $event->getSchema();

        foreach (self::INDEXES as $tableName => $indexes) {
            if (!$schema->hasTable($tableName)) {
                continue;
            }

            $table = $schema->getTable($tableName);
            foreach ($indexes as $indexName => $columns) {
                $this->useIndexName($table, $indexName, $columns);
            }
        }
    }

    /** @param list<string> $columns */
    private function useIndexName(Table $table, string $indexName, array $columns): void
    {
        if ($table->hasIndex($indexName)) {
            return;
        }

        foreach ($table->getIndexes() as $existingIndex) {
            if (!$existingIndex->isPrimary() && $existingIndex->getColumns() === $columns) {
                $table->renameIndex($existingIndex->getName(), $indexName);

                return;
            }
        }

        $table->addIndex($columns, $indexName);
    }
}
