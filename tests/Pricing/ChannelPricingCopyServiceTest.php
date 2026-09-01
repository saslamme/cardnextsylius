<?php

declare(strict_types=1);

namespace App\Tests\Pricing;

use App\Pricing\ChannelPricingCopyService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class ChannelPricingCopyServiceTest extends TestCase
{
    private ChannelPricingCopyService $service;

    protected function setUp(): void
    {
        $this->service = new ChannelPricingCopyService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(FactoryInterface::class),
            new NullLogger(),
        );
    }

    public function testAdjustmentUsesMinorUnitsWithoutFloatingPoint(): void
    {
        self::assertSame(129900, $this->service->adjustedPrice(129900, '0'));
        self::assertSame(136395, $this->service->adjustedPrice(129900, '5'));
        self::assertSame(123405, $this->service->adjustedPrice(129900, '-5'));
        self::assertSame(2, $this->service->adjustedPrice(1, '50'));
    }

    public function testAdjustmentThatWouldBeNegativeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->adjustedPrice(100, '-100.0001');
    }
}
