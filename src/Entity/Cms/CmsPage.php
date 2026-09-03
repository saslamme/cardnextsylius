<?php

declare(strict_types=1);

namespace App\Entity\Cms;

use App\Entity\Channel\Channel;
use App\Repository\Cms\CmsPageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use App\Validator\UniqueCmsPageSlugs;

#[ORM\Entity(repositoryClass: CmsPageRepository::class)]
#[ORM\Table(name: 'cardnext_cms_page')]
#[ORM\HasLifecycleCallbacks]
#[UniqueCmsPageSlugs]
#[UniqueEntity(fields: ['code'], message: 'Dieser CMS-Seitencode wird bereits verwendet.')]
class CmsPage
{
    public const STATUS_DRAFT = 'draft'; public const STATUS_PUBLISHED = 'published'; public const STATUS_DISABLED = 'disabled';
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 64, unique: true)] #[Assert\Regex('/^[a-z0-9_]+$/')] private string $code = '';
    #[ORM\ManyToOne] #[ORM\JoinColumn(name: 'layout_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')] private ?CmsLayout $layout = null;
    #[ORM\Column(length: 16)] #[Assert\Choice([self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_DISABLED])] private string $status = self::STATUS_DRAFT;
    #[ORM\Column(name: 'publish_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $publishAt = null;
    #[ORM\Column(name: 'unpublish_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $unpublishAt = null;
    #[ORM\Column(name: 'include_in_sitemap')] private bool $includeInSitemap = true;
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $updatedAt;
    /** @var Collection<int, Channel> */
    #[ORM\ManyToMany(targetEntity: Channel::class)]
    #[ORM\JoinTable(name: 'cardnext_cms_page_channel')]
    #[ORM\JoinColumn(name: 'cms_page_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'channel_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Assert\Count(min: 1, minMessage: 'Bitte mindestens einen Verkaufskanal auswählen.')]
    private Collection $channels;
    /** @var Collection<int, CmsPageTranslation> */
    #[ORM\OneToMany(mappedBy: 'page', targetEntity: CmsPageTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Count(min: 1, minMessage: 'Bitte mindestens eine Übersetzung ausfüllen.')]
    private Collection $translations;
    /** @var Collection<int, CmsBlock> */
    #[ORM\OneToMany(mappedBy: 'page', targetEntity: CmsBlock::class, cascade: ['persist', 'remove'], orphanRemoval: true)] #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;
    public function __construct() { $this->channels = new ArrayCollection(); $this->translations = new ArrayCollection(); $this->blocks = new ArrayCollection(); $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; } public function getCode(): string { return $this->code; } public function setCode(string $v): void { $this->code = strtolower(trim($v)); }
    public function getLayout(): ?CmsLayout { return $this->layout; } public function setLayout(?CmsLayout $v): void { $this->layout=$v; }
    public function getStatus(): string { return $this->status; } public function setStatus(string $v): void { $this->status=$v; }
    public function getPublishAt(): ?\DateTimeImmutable { return $this->publishAt; } public function setPublishAt(?\DateTimeImmutable $v): void {$this->publishAt=$v;}
    public function getUnpublishAt(): ?\DateTimeImmutable { return $this->unpublishAt; } public function setUnpublishAt(?\DateTimeImmutable $v): void {$this->unpublishAt=$v;}
    public function isIncludeInSitemap(): bool{return $this->includeInSitemap;} public function setIncludeInSitemap(bool $v):void{$this->includeInSitemap=$v;}
    /** @return Collection<int, Channel> */ public function getChannels(): Collection{return $this->channels;} public function addChannel(Channel $v):void{if(!$this->channels->contains($v))$this->channels->add($v);} public function removeChannel(Channel $v):void{$this->channels->removeElement($v);}
    /** @return Collection<int, CmsPageTranslation> */ public function getTranslations():Collection{return $this->translations;} public function addTranslation(CmsPageTranslation $v):void{if(!$this->translations->contains($v)){ $this->translations->add($v);$v->setPage($this);}}
    public function removeTranslation(CmsPageTranslation $translation): void { $this->translations->removeElement($translation); }
    public function getTranslation(string $locale):?CmsPageTranslation { foreach($this->translations as $t) if($t->getLocale()===$locale)return $t; return null; }
    /** @return Collection<int, CmsBlock> */ public function getBlocks():Collection{return $this->blocks;} public function addBlock(CmsBlock $v):void{if(!$this->blocks->contains($v)){ $this->blocks->add($v);$v->setPage($this);}}
    public function removeBlock(CmsBlock $block): void { $this->blocks->removeElement($block); }
    public function getUpdatedAt():\DateTimeImmutable{return $this->updatedAt;} #[ORM\PreUpdate] public function touch():void{$this->updatedAt=new \DateTimeImmutable();}

    #[Assert\Callback]
    public function validatePublicationWindow(ExecutionContextInterface $context): void
    {
        if ($this->publishAt !== null && $this->unpublishAt !== null && $this->publishAt >= $this->unpublishAt) {
            $context->buildViolation('Das Veröffentlichungsende muss nach dem Beginn liegen.')
                ->atPath('unpublishAt')
                ->addViolation();
        }

        $allowed = $this->layout?->getAllowedBlockTypes();
        if ($allowed !== null) {
            foreach ($this->blocks as $index => $block) {
                if (!in_array($block->getType(), $allowed, true)) {
                    $context->buildViolation('Dieser Blocktyp ist für das gewählte Layout nicht erlaubt.')
                        ->atPath(sprintf('blocks[%d].type', $index))
                        ->addViolation();
                }
            }
        }
    }
}
