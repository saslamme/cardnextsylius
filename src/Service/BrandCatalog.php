<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product\Manufacturer;
use App\Entity\Product\Product;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final class BrandCatalog
{
    public function __construct(private readonly Connection $connection, private readonly EntityManagerInterface $em, private readonly ChannelContextInterface $channelContext)
    {
    }

    /** @return list<Manufacturer> */
    public function manufacturers(): array
    {
        $ids = $this->connection->fetchFirstColumn($this->baseSql() . ' ORDER BY m.name', ['channel' => $this->channelCode()]);

        return $this->orderedEntities(Manufacturer::class, $ids);
    }

    public function manufacturer(string $slug): ?Manufacturer
    {
        $id = $this->connection->fetchOne($this->baseSql() . ' AND m.slug = :slug', ['channel' => $this->channelCode(), 'slug' => $slug]);

        return $id === false ? null : $this->em->find(Manufacturer::class, (int) $id);
    }

    /** @return list<Product> */
    public function products(Manufacturer $manufacturer, int $limit, int $offset): array
    {
        $ids = $this->connection->fetchFirstColumn($this->productSql() . ' AND p.manufacturer_id = :manufacturer GROUP BY p.id ORDER BY p.id DESC LIMIT :limit OFFSET :offset', [
            'channel' => $this->channelCode(), 'manufacturer' => $manufacturer->getId(), 'limit' => $limit, 'offset' => $offset,
        ], ['limit' => \PDO::PARAM_INT, 'offset' => \PDO::PARAM_INT]);

        return $this->orderedEntities(Product::class, $ids);
    }

    public function productCount(Manufacturer $manufacturer): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(DISTINCT available.id) FROM (' . $this->productSql() . ' AND p.manufacturer_id = :manufacturer) available', ['channel' => $this->channelCode(), 'manufacturer' => $manufacturer->getId()]);
    }

    /** @return list<array{name:string,slug:string}> */
    public function areas(Manufacturer $manufacturer, string $locale): array
    {
        return $this->connection->fetchAllAssociative('SELECT tt.name, tt.slug FROM sylius_product p JOIN sylius_product_channels pc ON pc.product_id = p.id JOIN sylius_channel c ON c.id = pc.channel_id JOIN sylius_product_variant v ON v.product_id = p.id JOIN sylius_channel_pricing cp ON cp.product_variant_id = v.id AND cp.channel_code = c.code JOIN sylius_product_taxon pt ON pt.product_id = p.id JOIN sylius_taxon t ON t.id = pt.taxon_id JOIN sylius_taxon_translation tt ON tt.translatable_id = t.id AND tt.locale = :locale WHERE p.enabled = 1 AND v.enabled = 1 AND cp.price IS NOT NULL AND c.code = :channel AND p.manufacturer_id = :manufacturer AND tt.name IS NOT NULL GROUP BY t.id, tt.name, tt.slug ORDER BY MIN(pt.position), tt.name LIMIT 4', ['channel' => $this->channelCode(), 'manufacturer' => $manufacturer->getId(), 'locale' => $locale]);
    }

    private function baseSql(): string
    {
        return 'SELECT DISTINCT m.id FROM cardnext_manufacturer m JOIN sylius_product p ON p.manufacturer_id = m.id JOIN sylius_product_channels pc ON pc.product_id = p.id JOIN sylius_channel c ON c.id = pc.channel_id JOIN sylius_product_variant v ON v.product_id = p.id JOIN sylius_channel_pricing cp ON cp.product_variant_id = v.id AND cp.channel_code = c.code WHERE m.enabled = 1 AND p.enabled = 1 AND v.enabled = 1 AND cp.price IS NOT NULL AND c.code = :channel';
    }

    private function productSql(): string
    {
        return 'SELECT DISTINCT p.id FROM sylius_product p JOIN sylius_product_channels pc ON pc.product_id = p.id JOIN sylius_channel c ON c.id = pc.channel_id JOIN sylius_product_variant v ON v.product_id = p.id JOIN sylius_channel_pricing cp ON cp.product_variant_id = v.id AND cp.channel_code = c.code WHERE p.enabled = 1 AND v.enabled = 1 AND cp.price IS NOT NULL AND c.code = :channel';
    }

    private function channelCode(): string
    {
        return (string) $this->channelContext->getChannel()->getCode();
    }

    private function orderedEntities(string $class, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $found = $this->em->getRepository($class)->findBy(['id' => array_map('intval', $ids)]);
        $indexed = [];
        foreach ($found as $entity) {
            $indexed[$entity->getId()] = $entity;
        }

        return array_values(array_filter(array_map(static fn ($id) => $indexed[(int) $id] ?? null, $ids)));
    }
}
