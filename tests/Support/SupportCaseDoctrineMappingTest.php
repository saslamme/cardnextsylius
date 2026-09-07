<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Support\SupportCase;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\TestCase;

final class SupportCaseDoctrineMappingTest extends TestCase
{
    public function testRelationsUseTheJoinColumnsFromTheDeployedSchema(): void
    {
        $metadata = new ClassMetadata(SupportCase::class);
        $driver = new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity/Support']);
        $driver->loadMetadataForClass(SupportCase::class, $metadata);

        self::assertSame('customer_id', $metadata->getSingleAssociationJoinColumnName('customer'));
        self::assertSame('order_id', $metadata->getSingleAssociationJoinColumnName('order'));
        self::assertSame('product_id', $metadata->getSingleAssociationJoinColumnName('product'));
        self::assertSame('maintenance_contract_id', $metadata->getSingleAssociationJoinColumnName('maintenanceContract'));
    }
}
