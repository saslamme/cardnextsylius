<?php

declare(strict_types=1);

namespace App\Entity\Channel;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Channel as BaseChannel;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_channel')]
class Channel extends BaseChannel
{
    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9_-]*$/')]
    private ?string $themeKey = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $brandName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoDarkPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $faviconPath = null;

    #[Assert\Image(maxSize: '2M', mimeTypes: ['image/png', 'image/webp', 'image/jpeg'])]
    public ?UploadedFile $logoFile = null;

    #[Assert\Image(maxSize: '2M', mimeTypes: ['image/png', 'image/webp', 'image/jpeg'])]
    public ?UploadedFile $logoDarkFile = null;

    #[Assert\Image(maxSize: '512k', mimeTypes: ['image/png', 'image/webp', 'image/jpeg'])]
    public ?UploadedFile $faviconFile = null;

    #[ORM\Column(length: 7, nullable: true)] #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/D')] private ?string $primaryColor = null;
    #[ORM\Column(length: 7, nullable: true)] #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/D')] private ?string $primaryHoverColor = null;
    #[ORM\Column(length: 7, nullable: true)] #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/D')] private ?string $primarySoftColor = null;
    #[ORM\Column(length: 7, nullable: true)] #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/D')] private ?string $inkColor = null;
    #[ORM\Column(length: 7, nullable: true)] #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/D')] private ?string $textColor = null;
    #[ORM\Column(length: 7, nullable: true)] #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/D')] private ?string $footerColor = null;

    public function getThemeKey(): ?string { return $this->themeKey; }
    public function setThemeKey(?string $value): void { $this->themeKey = $value ?: null; }
    public function getBrandName(): ?string { return $this->brandName; }
    public function setBrandName(?string $value): void { $this->brandName = $value ?: null; }
    public function getLogoPath(): ?string { return $this->logoPath; }
    public function setLogoPath(?string $value): void { $this->logoPath = $value; }
    public function getLogoDarkPath(): ?string { return $this->logoDarkPath; }
    public function setLogoDarkPath(?string $value): void { $this->logoDarkPath = $value; }
    public function getFaviconPath(): ?string { return $this->faviconPath; }
    public function setFaviconPath(?string $value): void { $this->faviconPath = $value; }

    public function getPrimaryColor(): ?string { return $this->primaryColor; }
    public function setPrimaryColor(?string $v): void { $this->primaryColor = $v ?: null; }
    public function getPrimaryHoverColor(): ?string { return $this->primaryHoverColor; }
    public function setPrimaryHoverColor(?string $v): void { $this->primaryHoverColor = $v ?: null; }
    public function getPrimarySoftColor(): ?string { return $this->primarySoftColor; }
    public function setPrimarySoftColor(?string $v): void { $this->primarySoftColor = $v ?: null; }
    public function getInkColor(): ?string { return $this->inkColor; }
    public function setInkColor(?string $v): void { $this->inkColor = $v ?: null; }
    public function getTextColor(): ?string { return $this->textColor; }
    public function setTextColor(?string $v): void { $this->textColor = $v ?: null; }
    public function getFooterColor(): ?string { return $this->footerColor; }
    public function setFooterColor(?string $v): void { $this->footerColor = $v ?: null; }
}
