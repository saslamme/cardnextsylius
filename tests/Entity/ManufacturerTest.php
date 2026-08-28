<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product\Manufacturer;
use PHPUnit\Framework\TestCase;

final class ManufacturerTest extends TestCase
{
    public function testNullSlugDoesNotCauseTypeErrorOrEraseExistingValue(): void
    {
        $manufacturer = new Manufacturer();
        $manufacturer->setSlug('zebra');

        $manufacturer->setSlug(null);

        self::assertSame('zebra', $manufacturer->getSlug());
    }
}
