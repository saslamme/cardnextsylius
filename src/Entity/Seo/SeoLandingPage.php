<?php

declare(strict_types=1);

namespace App\Entity\Seo;

use App\Entity\Channel\Channel;
use App\Entity\Taxonomy\Taxon;
use App\Repository\Seo\SeoLandingPageRepository;
use App\Seo\LandingPagePath;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Cms\CmsBlock;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: SeoLandingPageRepository::class)]
#[ORM\Table(name: 'cardnext_seo_landing_page')]
#[ORM\Index(name: 'idx_seo_landing_channel', columns: ['channel_id'])]
#[ORM\Index(name: 'idx_seo_landing_taxon', columns: ['base_taxon_id'])]
#[ORM\UniqueConstraint(name: 'uniq_seo_landing_channel_locale_path', columns: ['channel_id', 'locale', 'path'])]
#[UniqueEntity(fields: ['channel', 'locale', 'path'], message: 'Dieser URL-Pfad wird für diesen Verkaufskanal und diese Sprache bereits verwendet.')]
#[ORM\HasLifecycleCallbacks]
class SeoLandingPage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(name: 'internal_name', length: 255)] #[Assert\NotBlank] private string $internalName = '';
    #[ORM\Column(options: ['default' => true])] private bool $enabled = true;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?Channel $channel = null;
    #[ORM\Column(length: 12)] #[Assert\NotBlank] private string $locale = '';
    #[ORM\Column(length: 255)] #[Assert\NotBlank] private string $path = '';
    #[ORM\Column(length: 255)] #[Assert\NotBlank] private string $h1 = '';
    #[ORM\Column(name: 'meta_title', length: 255)] #[Assert\NotBlank] private string $metaTitle = '';
    #[ORM\Column(name: 'meta_description', length: 500, nullable: true)] private ?string $metaDescription = null;
    #[ORM\Column(name: 'top_content', type: Types::TEXT, nullable: true)] private ?string $topContent = null;
    #[ORM\Column(name: 'bottom_content', type: Types::TEXT, nullable: true)] private ?string $bottomContent = null;
    #[ORM\Column(name: 'robots_index', options: ['default' => true])] private bool $robotsIndex = true;
    #[ORM\Column(name: 'robots_follow', options: ['default' => true])] private bool $robotsFollow = true;
    #[ORM\Column(name: 'canonical_url', length: 2048, nullable: true)] #[Assert\Url(protocols: ['https'])] private ?string $canonicalUrl = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(name: 'base_taxon_id', nullable: false, onDelete: 'RESTRICT')] private ?Taxon $baseTaxon = null;
    /** @var array{manufacturers?: list<string>, attributes?: array<string, list<string>>} */
    #[ORM\Column(name: 'filter_definition', type: Types::JSON)] private array $filterDefinition = [];
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $updatedAt;
    /** @var Collection<int, CmsBlock> */
    #[ORM\OneToMany(mappedBy: 'seoLandingPage', targetEntity: CmsBlock::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    public function __construct() { $this->blocks = new ArrayCollection(); $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getInternalName(): string { return $this->internalName; }
    public function setInternalName(string $value): void { $this->internalName = trim($value); }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $value): void { $this->enabled = $value; }
    public function getChannel(): ?Channel { return $this->channel; }
    public function setChannel(Channel $value): void { $this->channel = $value; }
    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $value): void { $this->locale = trim($value); }
    public function getPath(): string { return $this->path; }
    public function setPath(string $value): void { $this->path = LandingPagePath::normalize($value); }
    public function getH1(): string { return $this->h1; }
    public function setH1(string $value): void { $this->h1 = trim($value); }
    public function getMetaTitle(): string { return $this->metaTitle; }
    public function setMetaTitle(string $value): void { $this->metaTitle = trim($value); }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $value): void { $this->metaDescription = self::nullable($value); }
    public function getTopContent(): ?string { return $this->topContent; }
    public function setTopContent(?string $value): void { $this->topContent = self::nullable($value); }
    public function getBottomContent(): ?string { return $this->bottomContent; }
    public function setBottomContent(?string $value): void { $this->bottomContent = self::nullable($value); }
    public function isRobotsIndex(): bool { return $this->robotsIndex; }
    public function setRobotsIndex(bool $value): void { $this->robotsIndex = $value; }
    public function isRobotsFollow(): bool { return $this->robotsFollow; }
    public function setRobotsFollow(bool $value): void { $this->robotsFollow = $value; }
    public function getRobots(): string { return ($this->robotsIndex ? 'index' : 'noindex') . ',' . ($this->robotsFollow ? 'follow' : 'nofollow'); }
    public function getCanonicalUrl(): ?string { return $this->canonicalUrl; }
    public function setCanonicalUrl(?string $value): void { $this->canonicalUrl = self::nullable($value); }
    public function getBaseTaxon(): ?Taxon { return $this->baseTaxon; }
    public function setBaseTaxon(Taxon $value): void { $this->baseTaxon = $value; }
    /** @return array{manufacturers?: list<string>, attributes?: array<string, list<string>>} */
    public function getFilterDefinition(): array { return $this->filterDefinition; }
    /** @param array<string, mixed> $value */
    public function setFilterDefinition(array $value): void { $this->filterDefinition = $value; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    /** @return Collection<int, CmsBlock> */ public function getBlocks(): Collection { return $this->blocks; }
    public function addBlock(CmsBlock $block): void { if (!$this->blocks->contains($block)) { $this->blocks->add($block); $block->setPage(null); $block->setSeoLandingPage($this); } }
    public function removeBlock(CmsBlock $block): void { if ($this->blocks->removeElement($block) && $block->getSeoLandingPage() === $this) { $block->setSeoLandingPage(null); } }
    #[Assert\Callback]
    public function validateChannelLocale(ExecutionContextInterface $context): void
    {
        if ($this->channel !== null && !$this->channel->getLocales()->exists(fn (int $key, $locale): bool => $locale->getCode() === $this->locale)) {
            $context->buildViolation('Die gewählte Sprache ist diesem Verkaufskanal nicht zugeordnet.')->atPath('locale')->addViolation();
        }
    }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    private static function nullable(?string $value): ?string { $value = trim((string) $value); return $value === '' ? null : $value; }
}
