<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Support\FreshdeskTicketStatusPresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FreshdeskTicketStatusPresenterTest extends TestCase
{
    #[DataProvider('statuses')]
    public function testStatusIsPresentedWithoutExposingAnUnknownTechnicalValue(int $status, string $key): void
    {
        self::assertSame($key, (new FreshdeskTicketStatusPresenter())->translationKey($status));
    }

    public static function statuses(): iterable
    {
        yield [2, 'cardnext.support.status.open'];
        yield [3, 'cardnext.support.status.pending'];
        yield [4, 'cardnext.support.status.resolved'];
        yield [5, 'cardnext.support.status.closed'];
        yield [99, 'cardnext.support.status.processing'];
    }
}
