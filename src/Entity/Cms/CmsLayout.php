<?php

declare(strict_types=1);

namespace App\Entity\Cms;

use App\Repository\Cms\CmsLayoutRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CmsLayoutRepository::class)]
#[ORM\Table(name: 'cardnext_cms_layout')]
#[ORM\HasLifecycleCallbacks]
class CmsLayout
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 64, unique: true)]
    #[Assert\Regex('/^[a-z0-9_]+$/')]
    private string $code = '';
    #[ORM\Column(length: 255)] private string $name = '';
    #[ORM\Column(length: 32)]
    #[Assert\Choice(['standard', 'wide', 'landing', 'service'])]
    private string $renderer = 'standard';
    #[ORM\Column] private bool $enabled = true;
    /** @var list<string>|null */
    #[ORM\Column(name: 'allowed_block_types', type: Types::JSON, nullable: true)]
    private ?array $allowedBlockTypes = null;
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $updatedAt;

    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): void { $this->code = strtolower(trim($code)); }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = trim($name); }
    public function getRenderer(): string { return $this->renderer; }
    public function setRenderer(string $renderer): void { $this->renderer = $renderer; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): void { $this->enabled = $enabled; }
    /** @return list<string>|null */ public function getAllowedBlockTypes(): ?array { return $this->allowedBlockTypes; }
    /** @param list<string>|null $types */ public function setAllowedBlockTypes(?array $types): void { $this->allowedBlockTypes = $types; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    public function __toString(): string { return $this->name; }
}
