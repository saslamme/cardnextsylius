<?php

declare(strict_types=1);

namespace App\Tests\Grid\Filter;

use App\Grid\Filter\CardnextManufacturerFilter;
use App\Grid\Filter\CardnextProductAttributeBooleanFilter;
use App\Grid\Filter\CardnextProductAttributeSelectFilter;
use App\Grid\Filter\FilterDataNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\GridBundle\Doctrine\ORM\DataSource;

final class CardnextProductFacetFilterTest extends TestCase
{
    /** @dataProvider normalizedPayloads */
    public function testFilterValuesAreNormalized(mixed $payload, array $expectedValues): void
    {
        self::assertSame($expectedValues, FilterDataNormalizer::values($payload));
    }

    public static function normalizedPayloads(): iterable
    {
        yield 'nested list' => [['value' => ['ZEBRA']], ['ZEBRA']];
        yield 'nested scalar' => [['value' => 'ZEBRA'], ['ZEBRA']];
        yield 'direct list' => [['ZEBRA', 'MATICA'], ['ZEBRA', 'MATICA']];
        yield 'direct scalar' => ['ZEBRA', ['ZEBRA']];
        yield 'empty nested list' => [['value' => []], []];
        yield 'null' => [null, []];
    }

    /** @dataProvider manufacturerPayloads */
    public function testManufacturerFilterAcceptsSyliusPayload(array $payload, array $expectedCodes): void
    {
        $dataSource = $this->dataSource();

        (new CardnextManufacturerFilter())->apply($dataSource, 'manufacturer', $payload, []);

        self::assertStringContainsString('cn_manufacturer.code IN(:cn_manufacturer_codes)', $dataSource->getQueryBuilder()->getDQL());
        self::assertSame($expectedCodes, $dataSource->getQueryBuilder()->getParameter('cn_manufacturer_codes')?->getValue());
    }

    public static function manufacturerPayloads(): iterable
    {
        yield 'one manufacturer' => [['value' => ['ZEBRA']], ['ZEBRA']];
        yield 'multiple manufacturers use one IN condition' => [['value' => ['ZEBRA', 'MATICA']], ['ZEBRA', 'MATICA']];
    }

    public function testEmptyManufacturerPayloadDoesNotChangeQuery(): void
    {
        $dataSource = $this->dataSource();
        $originalDql = $dataSource->getQueryBuilder()->getDQL();

        (new CardnextManufacturerFilter())->apply($dataSource, 'manufacturer', ['value' => []], []);

        self::assertSame($originalDql, $dataSource->getQueryBuilder()->getDQL());
    }

    /** @dataProvider selectAttributePayloads */
    public function testSelectAttributeFilterAcceptsSyliusPayload(array $payload, array $expectedValues): void
    {
        $dataSource = $this->dataSource();

        (new CardnextProductAttributeSelectFilter())->apply(
            $dataSource,
            'cn_printer_technology',
            $payload,
            ['attribute_code' => 'CN_PRINTER_TECHNOLOGY'],
        );

        $queryBuilder = $dataSource->getQueryBuilder();
        self::assertStringContainsString('cna_cn_printer_technology.code = :cn_code_cn_printer_technology', $queryBuilder->getDQL());
        foreach ($expectedValues as $index => $expectedValue) {
            self::assertSame('%"' . $expectedValue . '"%', $queryBuilder->getParameter('cn_value_cn_printer_technology_' . $index)?->getValue());
        }
    }

    public static function selectAttributePayloads(): iterable
    {
        yield 'one value' => [['value' => ['retransfer']], ['retransfer']];
        yield 'multiple values use OR' => [['value' => ['usb', 'ethernet']], ['usb', 'ethernet']];
    }

    public function testEmptySelectAttributePayloadDoesNotChangeQuery(): void
    {
        $dataSource = $this->dataSource();
        $originalDql = $dataSource->getQueryBuilder()->getDQL();

        (new CardnextProductAttributeSelectFilter())->apply(
            $dataSource,
            'connection',
            ['value' => []],
            ['attribute_code' => 'CONNECTION'],
        );

        self::assertSame($originalDql, $dataSource->getQueryBuilder()->getDQL());
    }

    /** @dataProvider booleanAttributePayloads */
    public function testBooleanAttributeFilterExtractsSyliusPayload(array $payload, bool $expectedValue): void
    {
        $dataSource = $this->dataSource();

        (new CardnextProductAttributeBooleanFilter())->apply(
            $dataSource,
            'duplex',
            $payload,
            ['attribute_code' => 'DUPLEX'],
        );

        self::assertSame($expectedValue, $dataSource->getQueryBuilder()->getParameter('cnb_value_duplex')?->getValue());
    }

    public static function booleanAttributePayloads(): iterable
    {
        yield 'true' => [['value' => '1'], true];
        yield 'false' => [['value' => '0'], false];
    }

    private function dataSource(): DataSource
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getExpressionBuilder')->willReturn(new Expr());
        $queryBuilder = new QueryBuilder($entityManager);
        $queryBuilder->select('o')->from('App\\Entity\\Product\\Product', 'o');

        return new DataSource($queryBuilder, true, true);
    }
}
