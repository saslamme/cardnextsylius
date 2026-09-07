<?php

declare(strict_types=1);

namespace App\Integration\Freshdesk;

use App\Integration\Freshdesk\Dto\FreshdeskConversationData;
use App\Integration\Freshdesk\Dto\FreshdeskCreateTicketData;
use App\Integration\Freshdesk\Dto\FreshdeskTicketData;

interface FreshdeskClientInterface
{
    public function checkConnection(): void;
    public function createTicket(FreshdeskCreateTicketData $data): FreshdeskTicketData;
    public function getTicket(int $ticketId): FreshdeskTicketData;
    /** @return list<FreshdeskConversationData> */
    public function getConversations(int $ticketId): array;
}
