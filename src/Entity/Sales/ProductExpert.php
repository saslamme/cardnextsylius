<?php

declare(strict_types=1);

namespace App\Entity\Sales;

use App\Entity\Channel\Channel;
use App\Entity\User\AdminUser;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_product_expert', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_product_expert_channel_slug', columns: ['channel_id', 'slug'])])]
#[UniqueEntity(fields: ['adminUser'])]
#[UniqueEntity(fields: ['channel', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class ProductExpert
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: AdminUser::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?AdminUser $adminUser = null;

    #[ORM\ManyToOne(targetEntity: Channel::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Channel $channel = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $displayName = '';

    #[ORM\Column(length: 150)]
    #[Assert\Regex(pattern: '/^(?!(?:admin|api|login|logout)$)[a-z0-9]+(?:-[a-z0-9]+)*$/D', message: 'Bitte einen URL-tauglichen, nicht reservierten Slug verwenden.')]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $introText = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProductExpertProduct> */
    #[ORM\OneToMany(mappedBy: 'expert', targetEntity: ProductExpertProduct::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $products;

    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); $this->products = new ArrayCollection(); }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getAdminUser(): ?AdminUser { return $this->adminUser; }
    public function setAdminUser(?AdminUser $value): void { $this->adminUser = $value; }
    public function getChannel(): ?Channel { return $this->channel; }
    public function setChannel(?Channel $value): void { $this->channel = $value; }
    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $value): void { $this->displayName = trim($value); }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $value): void { $this->slug = strtolower(trim($value)); }
    public function getIntroText(): ?string { return $this->introText; }
    public function setIntroText(?string $value): void { $this->introText = ($value = trim((string) $value)) === '' ? null : $value; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $value): void { $this->enabled = $value; }
    /** @return Collection<int, ProductExpertProduct> */ public function getProducts(): Collection { return $this->products; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function __toString(): string { return $this->displayName; }
}
