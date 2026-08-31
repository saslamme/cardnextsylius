<?php

declare(strict_types=1);

namespace App\Entity\Quote;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name:'cardnext_quote_request_history')]
class QuoteRequestHistory
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'history')]
    #[ORM\JoinColumn(
        name: 'quote_request_id',
        nullable: false,
        onDelete: 'CASCADE',
    )]
    private QuoteRequest $quoteRequest;

    #[ORM\Column(length:32)]
    private string $type;

    #[ORM\Column(name:'old_status', length:32, nullable:true)]
    private ?string $oldStatus;

    #[ORM\Column(name:'new_status', length:32, nullable:true)]
    private ?string $newStatus;

    #[ORM\Column(type:Types::TEXT, nullable:true)]
    private ?string $message;

    #[ORM\Column(name:'created_at', type:Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $type, ?string $old = null, ?string $new = null, ?string $message = null)
    {
        $this->type = $type;
        $this->oldStatus = $old;
        $this->newStatus = $new;
        $this->message = $message;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuoteRequest(): QuoteRequest
    {
        return $this->quoteRequest;
    }

    public function setQuoteRequest(QuoteRequest $v): void
    {
        $this->quoteRequest = $v;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOldStatus(): ?string
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): ?string
    {
        return $this->newStatus;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
