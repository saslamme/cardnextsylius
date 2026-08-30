<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Order\Order;
use App\Entity\Quote\Quote;
use App\Enum\Quote\QuoteStatus;
use App\Service\Quote\QuoteOrderConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuoteOrderConversionPhase2dTest extends TestCase
{
    /** @return iterable<string, array{QuoteStatus}> */
    public static function nonAcceptedStatuses(): iterable
    {
        foreach ([QuoteStatus::Draft, QuoteStatus::Ready, QuoteStatus::Sent, QuoteStatus::Rejected, QuoteStatus::Superseded] as $status) yield $status->value => [$status];
    }

    #[DataProvider('nonAcceptedStatuses')]
    public function testOnlyAcceptedQuotesAreEligible(QuoteStatus $status): void
    {
        self::assertNotSame(QuoteStatus::Accepted, $status);
    }

    public function testConversionAuditCanOnlyBeRecordedOnce(): void
    {
        $quote = new Quote();
        $order = new Order();
        $now = new \DateTimeImmutable('2026-08-30 14:00:00');
        $quote->markConvertedToOrder($order, null, $now);

        self::assertSame($order, $quote->getOrder());
        self::assertSame($now, $quote->getConvertedToOrderAt());
        self::assertNull($quote->getConvertedToOrderBy());
        $this->expectException(\DomainException::class);
        $quote->markConvertedToOrder(new Order(), null, $now);
    }

    public function testContactNameIsSplitDeterministically(): void
    {
        self::assertSame(['Sascha', 'Lammers'], QuoteOrderConverter::splitContactName(' Sascha Lammers '));
        self::assertSame(['Sascha', '-'], QuoteOrderConverter::splitContactName('Sascha'));
        self::assertSame(['-', '-'], QuoteOrderConverter::splitContactName(''));
    }

    public function testAdminConversionUsesPostCsrfAndSwitchesToOrderLink(): void
    {
        $template = (string) file_get_contents(__DIR__.'/../../templates/admin/cardnext/quote/edit.html.twig');
        self::assertStringContainsString("method=\"post\" action=\"{{ path('cardnext_admin_quote_order_create'", $template);
        self::assertStringContainsString("csrf_token('quote_order_create_'~quote.id)", $template);
        self::assertStringContainsString("quote.status.value == 'accepted' and quote.order is null", $template);
        self::assertStringContainsString("path('sylius_admin_order_show'", $template);
    }
}
