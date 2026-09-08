<?php

declare(strict_types=1);

namespace App\Entity\Leasing;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_leasing_configuration')]
#[ORM\HasLifecycleCallbacks]
class LeasingConfiguration
{
    public const DEFAULT_NOTICE = 'Leasingangebot für Gewerbekunden. Vorbehaltlich Bonitätsprüfung und Vertragsannahme durch abcfinance GmbH. Die tatsächlichen Konditionen können abweichen.';
    #[ORM\Id, ORM\Column] private int $id = 1;
    #[ORM\Column] private bool $enabled = false;
    #[Assert\NotBlank] #[ORM\Column(length: 255)] private string $providerName = 'abcfinance / Lease Seven';
    #[Assert\PositiveOrZero] #[ORM\Column(name: 'minimum_net_amount')] private int $minimumNetAmount = 100000;
    #[ORM\ManyToOne(targetEntity: LeasingFactor::class)] #[ORM\JoinColumn(onDelete: 'SET NULL')] private ?LeasingFactor $defaultFactor = null;
    #[Assert\NotBlank] #[ORM\Column(type: Types::TEXT)] private string $frontendNotice = self::DEFAULT_NOTICE;
    #[Assert\NotBlank] #[ORM\Column(length: 100)] private string $ctaText = 'Leasing anfragen';
    #[Assert\Email] #[ORM\Column(length: 254, nullable: true)] private ?string $notificationEmail = null;
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $updatedAt;
    public function __construct() { $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): int { return $this->id; }
    public function isEnabled(): bool { return $this->enabled; } public function setEnabled(bool $v): void { $this->enabled=$v; }
    public function getProviderName(): string { return $this->providerName; } public function setProviderName(string $v): void { $this->providerName=$v; }
    public function getMinimumNetAmount(): int { return $this->minimumNetAmount; } public function setMinimumNetAmount(int $v): void { $this->minimumNetAmount=$v; }
    public function getDefaultFactor(): ?LeasingFactor { return $this->defaultFactor; } public function setDefaultFactor(?LeasingFactor $v): void { $this->defaultFactor=$v; }
    public function getFrontendNotice(): string { return $this->frontendNotice; } public function setFrontendNotice(string $v): void { $this->frontendNotice=$v; }
    public function getCtaText(): string { return $this->ctaText; } public function setCtaText(string $v): void { $this->ctaText=$v; }
    public function getNotificationEmail(): ?string { return $this->notificationEmail; } public function setNotificationEmail(?string $v): void { $this->notificationEmail=$v; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt=new \DateTimeImmutable(); }
}
