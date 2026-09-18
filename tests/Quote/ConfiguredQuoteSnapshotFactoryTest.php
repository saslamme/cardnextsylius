<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Order\ConfiguredOrderItem;
use App\Service\Quote\ConfiguredQuoteSnapshotFactory;
use PHPUnit\Framework\TestCase;

final class ConfiguredQuoteSnapshotFactoryTest extends TestCase
{
    public function testAcceptedQuotePriceReplacesOnlyTheSnapshotTotal(): void
    {
        $source = new ConfiguredOrderItem('cards', 'Printed cards', 'de_DE', 'WEB', 'EUR', 500, 'hash', ['material' => ['label' => 'Material', 'value' => 'PVC']], [['label' => 'Base', 'amount' => 12000]], ['quantity' => 500, 'leadTimeCode' => 'standard', 'selections' => ['material' => 'pvc']], 20, 4, 24, 12000, 500, 0, 12500, 'standard', 'Standard', 8, 'standard', true);
        $factory = new ConfiguredQuoteSnapshotFactory();
        $snapshot = $factory->createSnapshot($source);
        $restored = $factory->restore($snapshot, 11500);

        self::assertSame(11500, $restored->getTotal());
        self::assertSame($source->getCanonicalConfiguration(), $restored->getCanonicalConfiguration());
        self::assertSame($source->getSelectionsSnapshot(), $restored->getSelectionsSnapshot());
        self::assertSame($source->getPriceBreakdownSnapshot(), $restored->getPriceBreakdownSnapshot());
        self::assertSame('standard', $restored->getLeadTimeCode());
        self::assertSame(8, $restored->getWorkingDays());
    }
}
