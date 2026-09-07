<?php
declare(strict_types=1);
namespace App\Integration\Freshdesk\Dto;

final readonly class FreshdeskCreateTicketData
{
    public function __construct(public string $email, public string $name, public string $subject, public string $description)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new \InvalidArgumentException('A valid requester email is required.'); }
        if (trim($subject) === '' || trim($description) === '') { throw new \InvalidArgumentException('Subject and description are required.'); }
    }
}
