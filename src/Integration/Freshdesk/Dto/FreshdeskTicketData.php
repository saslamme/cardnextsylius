<?php
declare(strict_types=1);
namespace App\Integration\Freshdesk\Dto;

final readonly class FreshdeskTicketData
{
    public function __construct(public int $id, public int $requesterId, public string $subject, public string $descriptionText, public ?string $descriptionHtml, public int $status, public int $priority, public ?int $responderId, public \DateTimeImmutable $createdAt, public \DateTimeImmutable $updatedAt) {}
}
