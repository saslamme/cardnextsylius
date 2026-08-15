<?php

declare(strict_types=1);

namespace App\Entity\Content;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_legal_page')]
#[ORM\UniqueConstraint(name: 'UNIQ_CARDNEXT_LEGAL_PAGE_CODE_LOCALE', columns: ['code', 'locale_code'])]
#[ORM\HasLifecycleCallbacks]
class LegalPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $code = '';

    #[ORM\Column(name: 'locale_code', length: 12)]
    private string $localeCode = 'de_DE';

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(name: 'meta_title', length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(name: 'meta_description', length: 500, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): void { $this->code = $code; }
    public function getLocaleCode(): string { return $this->localeCode; }
    public function setLocaleCode(string $localeCode): void { $this->localeCode = $localeCode; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $content): void { $this->content = $content; }
    public function getMetaTitle(): ?string { return $this->metaTitle; }
    public function setMetaTitle(?string $metaTitle): void { $this->metaTitle = $metaTitle; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $metaDescription): void { $this->metaDescription = $metaDescription; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
