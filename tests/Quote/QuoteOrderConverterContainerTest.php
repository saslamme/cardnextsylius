<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Service\Quote\QuoteOrderConverter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QuoteOrderConverterContainerTest extends KernelTestCase
{
    public function testConverterCanBeInstantiatedByTheContainer(): void
    {
        self::bootKernel();

        self::assertInstanceOf(
            QuoteOrderConverter::class,
            self::getContainer()->get(QuoteOrderConverter::class),
        );
    }
}
