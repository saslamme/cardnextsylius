<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_product_document')]
class ProductDocument
{
    public const TYPE_DATASHEET = 'datasheet';

    public const TYPE_MANUAL = 'manual';

    public const TYPE_DRIVER = 'driver';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_BROCHURE = 'brochure';

    public const TYPE_OTHER = 'other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 64)]
    private string $type = self::TYPE_DATASHEET;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $locale = null;

    #[ORM\Column(name: 'import_key', length: 100, nullable: true)]
    private ?string $importKey = null;

    #[ORM\Column(name: 'file_path', length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(name: 'original_filename', length: 255, nullable: true)]
    private ?string $originalFilename = null;

    #[ORM\Column(name: 'mime_type', length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(name: 'file_size', type: 'integer', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = trim($title);
        $this->touch();
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->touch();
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_DATASHEET => 'Datenblatt',
            self::TYPE_MANUAL => 'Handbuch',
            self::TYPE_DRIVER => 'Treiber / Software',
            self::TYPE_CERTIFICATE => 'Zertifikat',
            self::TYPE_BROCHURE => 'Broschüre',
            default => 'Dokument',
        };
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $locale = $locale !== null ? trim($locale) : null;
        $this->locale = $locale !== '' ? $locale : null;
        $this->touch();
    }

    public function getImportKey(): ?string
    {
        return $this->importKey;
    }

    public function setImportKey(?string $importKey): void
    {
        $importKey = $importKey !== null ? trim($importKey) : null;
        $this->importKey = $importKey !== '' ? $importKey : null;
        $this->touch();
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): void
    {
        $this->filePath = $filePath;
        $this->touch();
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(?string $originalFilename): void
    {
        $this->originalFilename = $originalFilename;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function getFormattedFileSize(): ?string
    {
        if ($this->fileSize === null) {
            return null;
        }

        if ($this->fileSize >= 1_048_576) {
            return number_format($this->fileSize / 1_048_576, 1, ',', '.') . ' MB';
        }

        return number_format($this->fileSize / 1024, 0, ',', '.') . ' KB';
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
        $this->touch();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        // @phpstan-ignore isset.initializedProperty
        if (isset($this->createdAt)) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }
}
