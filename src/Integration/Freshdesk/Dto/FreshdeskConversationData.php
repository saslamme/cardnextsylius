<?php
declare(strict_types=1);
namespace App\Integration\Freshdesk\Dto;

/** The private flag is a security boundary: private notes must never be exposed to customers. */
final readonly class FreshdeskConversationData
{
    public function __construct(public int $id, public int $ticketId, public ?int $userId, public bool $incoming, public bool $private, public string $bodyText, public ?string $bodyHtml, public \DateTimeImmutable $createdAt, public \DateTimeImmutable $updatedAt) {}
}
