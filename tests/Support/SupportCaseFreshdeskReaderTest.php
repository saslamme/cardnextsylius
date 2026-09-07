<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Customer\Customer;
use App\Entity\Support\SupportCase;
use App\Enum\Support\SupportCaseType;
use App\Integration\Freshdesk\Dto\FreshdeskConversationData;
use App\Integration\Freshdesk\Dto\FreshdeskTicketData;
use App\Integration\Freshdesk\FreshdeskClientInterface;
use App\Service\Support\SupportCaseAccessDeniedException;
use App\Service\Support\SupportCaseFreshdeskReader;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SupportCaseFreshdeskReaderTest extends TestCase
{
    public function testRequesterMismatchPreventsConversationLoading(): void
    {
        $customer = new Customer();
        $case = new SupportCase($customer, 42, 100, 'WEB', SupportCaseType::Other, new \DateTimeImmutable());
        $client = $this->createMock(FreshdeskClientInterface::class);
        $client->expects(self::once())->method('getTicket')->with(42)->willReturn($this->ticket(200));
        $client->expects(self::never())->method('getConversations');

        $this->expectException(SupportCaseAccessDeniedException::class);
        (new SupportCaseFreshdeskReader($client, new NullLogger()))->getConversationsForCustomer($case, $customer);
    }

    public function testPrivateNotesAreNeverReturned(): void
    {
        $customer = new Customer();
        $case = new SupportCase($customer, 42, 100, 'WEB', SupportCaseType::Other, new \DateTimeImmutable());
        $client = $this->createMock(FreshdeskClientInterface::class);
        $client->method('getTicket')->willReturn($this->ticket(100));
        $client->method('getConversations')->willReturn([
            $this->conversation(1, false, 'Public reply'),
            $this->conversation(2, true, 'Private note'),
        ]);

        $conversations = (new SupportCaseFreshdeskReader($client, new NullLogger()))->getConversationsForCustomer($case, $customer);

        self::assertCount(1, $conversations);
        self::assertSame('Public reply', $conversations[0]->bodyText);
    }

    private function ticket(int $requesterId): FreshdeskTicketData
    {
        $now = new \DateTimeImmutable();

        return new FreshdeskTicketData(42, $requesterId, 'Subject', 'Description', null, 2, 1, null, $now, $now);
    }

    private function conversation(int $id, bool $private, string $text): FreshdeskConversationData
    {
        $now = new \DateTimeImmutable();

        return new FreshdeskConversationData($id, 42, null, false, $private, $text, null, $now, $now);
    }
}
