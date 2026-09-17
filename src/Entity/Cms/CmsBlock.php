<?php

declare(strict_types=1);

namespace App\Entity\Cms;

use App\Entity\Seo\SeoLandingPage;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_cms_block')]
#[ORM\Index(name: 'idx_cms_block_page', columns: ['page_id'])]
#[ORM\Index(name: 'IDX_CMS_BLOCK_RENDER', columns: ['page_id', 'locale', 'enabled', 'position'])]
#[ORM\Index(name: 'idx_cms_block_seo_landing_page', columns: ['seo_landing_page_id'])]
#[ORM\Index(name: 'idx_cms_block_seo_render', columns: ['seo_landing_page_id', 'placement', 'enabled', 'position'])]
#[ORM\HasLifecycleCallbacks]
class CmsBlock
{
    public const PLACEMENT_CONTENT = 'content';
    public const PLACEMENT_BEFORE_CATALOG = 'before_catalog';
    public const PLACEMENT_AFTER_CATALOG = 'after_catalog';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'blocks')] #[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')] private ?CmsPage $page = null;
    #[ORM\ManyToOne(inversedBy: 'blocks')] #[ORM\JoinColumn(name: 'seo_landing_page_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')] private ?SeoLandingPage $seoLandingPage = null;
    #[ORM\Column(length: 12)] private string $locale = '';
    #[ORM\Column(length: 32)] private string $type = 'rich_text';
    #[ORM\Column(length: 32, options: ['default' => self::PLACEMENT_CONTENT])] #[Assert\Choice(choices: [self::PLACEMENT_CONTENT, self::PLACEMENT_BEFORE_CATALOG, self::PLACEMENT_AFTER_CATALOG])] private string $placement = self::PLACEMENT_CONTENT;
    #[ORM\Column] private int $position = 0;
    #[ORM\Column] private bool $enabled = true;
    /** @var array<string, mixed> */ #[ORM\Column(type: Types::JSON)] private array $configuration = [];
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $updatedAt;

    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getPage(): ?CmsPage { return $this->page; }
    public function setPage(?CmsPage $value): void { $this->page = $value; }
    public function getSeoLandingPage(): ?SeoLandingPage { return $this->seoLandingPage; }
    public function setSeoLandingPage(?SeoLandingPage $value): void { $this->seoLandingPage = $value; }
    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $value): void { $this->locale = $value; }
    public function getType(): string { return $this->type; }
    public function setType(string $value): void { $this->type = $value; }
    public function getPlacement(): string { return $this->placement; }
    public function setPlacement(string $value): void { $this->placement = $value; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $value): void { $this->position = $value; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $value): void { $this->enabled = $value; }
    /** @return array<string, mixed> */ public function getConfiguration(): array { return $this->configuration; }
    /** @param array<string, mixed> $value */ public function setConfiguration(array $value): void { $this->configuration = $value; }

    #[Assert\Callback]
    public function validateOwner(ExecutionContextInterface $context): void
    {
        if (($this->page === null) === ($this->seoLandingPage === null)) {
            $context->buildViolation('Ein Inhaltselement muss genau einer CMS-Seite oder SEO-Landingpage zugeordnet sein.')->atPath('page')->addViolation();
        }
        if ($this->page !== null && $this->placement !== self::PLACEMENT_CONTENT) {
            $context->buildViolation('CMS-Seiten unterstützen nur den Inhaltsbereich content.')->atPath('placement')->addViolation();
        }
    }

    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
