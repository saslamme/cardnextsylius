<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_manufacturer')]
#[UniqueEntity(fields: ['code'], message: 'Dieser Hersteller-Code ist bereits vergeben.')]
#[UniqueEntity(fields: ['slug'], message: 'Dieser Hersteller-Slug ist bereits vergeben.')]
class Manufacturer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'logo_path', length: 255, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $featured = false;

    #[ORM\Column(name: 'featured_position', options: ['default' => 100])]
    private int $featuredPosition = 100;

    #[ORM\Column(name: 'seo_title', length: 255, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(name: 'seo_description', length: 320, nullable: true)]
    private ?string $seoDescription = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = strtoupper(trim($code));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = strtolower(trim($slug));
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): void
    {
        $this->featured = $featured;
    }

    public function getFeaturedPosition(): int
    {
        return $this->featuredPosition;
    }

    public function setFeaturedPosition(int $position): void
    {
        $this->featuredPosition = $position;
    }

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function setSeoTitle(?string $value): void
    {
        $this->seoTitle = ($value = trim((string) $value)) !== '' ? $value : null;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function setSeoDescription(?string $value): void
    {
        $this->seoDescription = ($value = trim((string) $value)) !== '' ? $value : null;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): void
    {
        $website = $website !== null ? trim($website) : null;
        $this->website = $website !== '' ? $website : null;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $description = $description !== null ? trim($description) : null;
        $this->description = $description !== '' ? $description : null;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): void
    {
        $this->logoPath = $logoPath;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : $this->code;
    }
}
