<?php

declare(strict_types=1);

namespace App\Service\Support;

use App\Entity\Customer\Customer;
use App\Entity\Support\SupportCase;
use App\Integration\Freshdesk\Dto\FreshdeskTicketData;
use App\Integration\Freshdesk\FreshdeskClientInterface;
use Psr\Log\LoggerInterface;

final readonly class SupportCaseFreshdeskReader
{
    public function __construct(private FreshdeskClientInterface $client, private LoggerInterface $logger)
    {
    }

    public function getTicketForCustomer(SupportCase $case, Customer $customer): FreshdeskTicketData
    {
        $this->assertOwnership($case, $customer);
        $ticket = $this->client->getTicket($case->getFreshdeskTicketId());
        $this->assertRequester($case, $ticket);

        return $ticket;
    }

    /** @return list<\App\Integration\Freshdesk\Dto\FreshdeskConversationData> */
    public function getConversationsForCustomer(SupportCase $case, Customer $customer): array
    {
        // Validate the remote mapping before any conversation data crosses the account boundary.
        $this->getTicketForCustomer($case, $customer);

        return array_values(array_filter(
            $this->client->getConversations($case->getFreshdeskTicketId()),
            static fn ($conversation): bool => !$conversation->private,
        ));
    }

    private function assertOwnership(SupportCase $case, Customer $customer): void
    {
        if ($case->getCustomer() !== $customer) {
            throw new SupportCaseAccessDeniedException('The support case does not belong to this customer.');
        }
    }

    private function assertRequester(SupportCase $case, FreshdeskTicketData $ticket): void
    {
        if ($ticket->requesterId === $case->getFreshdeskRequesterId()) {
            return;
        }

        $this->logger->warning('Freshdesk requester does not match the local support case.', [
            'support_case_id' => $case->getId(),
            'freshdesk_ticket_id' => $case->getFreshdeskTicketId(),
        ]);

        throw new SupportCaseAccessDeniedException('The remote requester does not match this support case.');
    }
}
