<?php

declare(strict_types=1);

namespace App\Entity\Leasing;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_leasing_factor')]
#[ORM\UniqueConstraint(name: 'UNIQ_LEASING_DURATION_ACTIVE', columns: ['duration_months', 'active_key'])]
#[ORM\HasLifecycleCallbacks]
class LeasingFactor
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[Assert\Positive] #[ORM\Column(name: 'duration_months')] private int $durationMonths = 0;
    #[Assert\Positive] #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 8)] private string $factor = '0';
    #[ORM\Column] private bool $active = true;
    /** MySQL permits multiple NULL values, while still enforcing one active duration. */
    #[ORM\Column(name: 'active_key', nullable: true, options: ['default' => 1])] private ?int $activeKey = 1;
    #[Assert\PositiveOrZero] #[ORM\Column] private int $position = 0;
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $updatedAt;

    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getDurationMonths(): int { return $this->durationMonths; }
    public function setDurationMonths(int $value): void { $this->durationMonths = $value; }
    public function getFactor(): string { return $this->factor; }
    public function setFactor(string $value): void { $this->factor = trim(str_replace(',', '.', $value)); }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $value): void { $this->active = $value; $this->activeKey = $value ? 1 : null; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $value): void { $this->position = $value; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    public function __toString(): string { return sprintf('%d Monate', $this->durationMonths); }
}
