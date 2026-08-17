<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CardnextCheckPublicSlugsCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CardnextCheckPublicSlugsCommandTest extends TestCase
{
    public function testItReportsNoCollisions(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([]);

        $tester = new CommandTester(new CardnextCheckPublicSlugsCommand($connection));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('No product/taxon public-slug collisions found', $tester->getDisplay());
    }

    public function testItReportsEveryCollisionWithoutChangingData(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')->willReturn([[
            'locale' => 'de_DE',
            'slug' => 'kartenjojos',
            'product_code' => 'PRODUCT',
            'taxon_code' => 'TAXON',
        ]]);

        $tester = new CommandTester(new CardnextCheckPublicSlugsCommand($connection));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('kartenjojos', $tester->getDisplay());
        self::assertStringContainsString('PRODUCT', $tester->getDisplay());
        self::assertStringContainsString('TAXON', $tester->getDisplay());
    }
}
