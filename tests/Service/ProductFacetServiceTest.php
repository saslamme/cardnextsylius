<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Taxonomy\Taxon;
use App\Service\ProductAttributeProfileService;
use App\Service\ProductFacetService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;

final class ProductFacetServiceTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function categoryRequests(): iterable
    {
        yield 'normal taxon page with products and facets' => [[], true];
        yield 'valid manufacturer filter' => [['criteria' => ['manufacturer' => ['value' => ['acme']]]], true];
        yield 'valid attribute filter' => [['criteria' => ['cn_printer_technology' => ['value' => ['direct_to_card']]]], true];
        yield 'manufacturer and attribute filters' => [[
            'criteria' => [
                'manufacturer' => ['value' => ['acme']],
                'cn_printer_technology' => ['value' => ['direct_to_card']],
            ],
        ], true];
        yield 'unknown grid filter is ignored by facet discovery' => [['criteria' => ['not_registered' => ['value' => ['anything']]]], true];
        yield 'valid but non-matching attribute value is ignored' => [['criteria' => ['cn_printer_technology' => ['value' => ['unknown']]]], true];
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('categoryRequests')]
    public function testCategoryFacetRequestsDoNotFailDuringGridConstruction(array $query, bool $hasResults): void
    {
        $service = $this->createService($this->createDatabase());
        $facets = $service->getFacets(
            $this->createTaxon(1, 10),
            $this->createChannel(),
            new Request($query, [], ['_locale' => 'de_DE']),
            'card_printers',
        );

        self::assertSame($hasResults, $facets['manufacturer'] !== []);
        self::assertSame($hasResults, $facets['attributes'] !== []);
        self::assertSame('Acme', $facets['manufacturer']['acme']['label'] ?? null);
        self::assertSame(1, $facets['attributes']['CN_PRINTER_TECHNOLOGY']['direct_to_card'] ?? null);
    }

    public function testEmptyTaxonWithFacetsReturnsNoChoicesInsteadOfFailing(): void
    {
        $facets = $this->createService($this->createDatabase())->getFacets(
            $this->createTaxon(20, 30),
            $this->createChannel(),
            new Request([], [], ['_locale' => 'de_DE']),
            'card_printers',
        );

        self::assertSame(['manufacturer' => [], 'attributes' => []], $facets);
    }

    public function testTaxonWithoutFacetDefinitionsDoesNotQueryProducts(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchFirstColumn');

        $facets = $this->createService($connection)->getFacets(
            $this->createTaxon(1, 10),
            $this->createChannel(),
            new Request([], [], ['_locale' => 'de_DE']),
            'taxon_without_profile',
        );

        self::assertSame(['manufacturer' => [], 'attributes' => []], $facets);
    }

    public function testAccessoriesWithUnknownStoredChoiceStillReturnKnownFacets(): void
    {
        $connection = $this->createDatabase();
        $connection->executeStatement("INSERT INTO sylius_product_attribute VALUES (41, 'CN_ACCESSORY_TYPE')");
        $connection->executeStatement("INSERT INTO sylius_product_attribute_value VALUES (30, 41, '\"legacy_reel_value\"', NULL, NULL, NULL, NULL)");

        $facets = $this->createService($connection)->getFacets(
            $this->createTaxon(1, 10),
            $this->createChannel(),
            new Request(['criteria' => ['cn_accessory_type' => ['value' => ['invalid']]]], [], ['_locale' => 'de_DE']),
            'id_accessories',
        );

        self::assertSame('Acme', $facets['manufacturer']['acme']['label']);
        self::assertSame([], $facets['attributes']);
    }

    private function createDatabase(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = [
            'CREATE TABLE sylius_product (id INTEGER PRIMARY KEY, enabled INTEGER, manufacturer_id INTEGER)',
            'CREATE TABLE sylius_product_channels (product_id INTEGER, channel_id INTEGER)',
            'CREATE TABLE sylius_product_taxon (product_id INTEGER, taxon_id INTEGER)',
            'CREATE TABLE sylius_taxon (id INTEGER PRIMARY KEY, tree_root INTEGER, tree_left INTEGER, tree_right INTEGER)',
            'CREATE TABLE cardnext_manufacturer (id INTEGER PRIMARY KEY, code TEXT, name TEXT, enabled INTEGER, position INTEGER)',
            'CREATE TABLE sylius_product_attribute (id INTEGER PRIMARY KEY, code TEXT)',
            'CREATE TABLE sylius_product_attribute_value (product_id INTEGER, attribute_id INTEGER, json_value TEXT, boolean_value INTEGER, integer_value INTEGER, float_value REAL, text_value TEXT)',
        ];
        foreach ($schema as $statement) {
            $connection->executeStatement($statement);
        }
        $connection->executeStatement('INSERT INTO sylius_taxon VALUES (10, 10, 1, 10), (11, 10, 2, 3)');
        $connection->executeStatement("INSERT INTO cardnext_manufacturer VALUES (20, 'acme', 'Acme', 1, 1)");
        $connection->executeStatement('INSERT INTO sylius_product VALUES (30, 1, 20)');
        $connection->executeStatement('INSERT INTO sylius_product_channels VALUES (30, 7)');
        $connection->executeStatement('INSERT INTO sylius_product_taxon VALUES (30, 11)');
        $connection->executeStatement("INSERT INTO sylius_product_attribute VALUES (40, 'CN_PRINTER_TECHNOLOGY')");
        $connection->executeStatement("INSERT INTO sylius_product_attribute_value VALUES (30, 40, '\"direct_to_card\"', NULL, NULL, NULL, NULL)");

        return $connection;
    }

    private function createService(Connection $connection): ProductFacetService
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return new ProductFacetService($entityManager, new ProductAttributeProfileService($entityManager));
    }

    private function createTaxon(int $left, int $right): Taxon
    {
        $root = $this->createMock(Taxon::class);
        $root->method('getId')->willReturn(10);
        $taxon = $this->createMock(Taxon::class);
        $taxon->method('getRoot')->willReturn($root);
        $taxon->method('getLeft')->willReturn($left);
        $taxon->method('getRight')->willReturn($right);

        return $taxon;
    }

    private function createChannel(): ChannelInterface
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getId')->willReturn(7);

        return $channel;
    }
}
