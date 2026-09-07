<?php

declare(strict_types=1);

namespace App\Tests\Freshdesk;

use App\Command\FreshdeskCheckCommand;
use App\Integration\Freshdesk\FreshdeskClientInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class FreshdeskCheckCommandTest extends TestCase
{
    public function testSuccessfulCheckReportsThatFreshdeskIsReachable(): void
    {
        $client = $this->createMock(FreshdeskClientInterface::class);
        $client->expects(self::once())->method('checkConnection');
        $tester = new CommandTester(new FreshdeskCheckCommand($client));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Freshdesk API is reachable.', $tester->getDisplay());
    }
}
