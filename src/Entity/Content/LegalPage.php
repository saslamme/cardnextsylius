<?php

declare(strict_types=1);

namespace App\Entity\Content;

use App\Entity\Channel\Channel;
use App\Repository\Content\LegalPageRepository;
use App\Validator\UniqueLegalPageChannels;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LegalPageRepository::class)]
#[ORM\Table(name: 'cardnext_legal_page')]
#[ORM\HasLifecycleCallbacks]
#[UniqueLegalPageChannels]
class LegalPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected int $id;

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

    /** @var Collection<int, Channel> */
    #[ORM\ManyToMany(targetEntity: Channel::class)]
    #[ORM\JoinTable(name: 'cardnext_legal_page_channel')]
    #[ORM\JoinColumn(name: 'legal_page_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'channel_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Assert\Count(min: 1, minMessage: 'Bitte wählen Sie mindestens einen Verkaufskanal aus.')]
    private Collection $channels;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->channels = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function setLocaleCode(string $localeCode): void
    {
        $this->localeCode = $localeCode;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    /** @return Collection<int, Channel> */
    public function getChannels(): Collection
    {
        return $this->channels;
    }

    public function addChannel(Channel $channel): void
    {
        if (!$this->channels->contains($channel)) {
            $this->channels->add($channel);
        }
    }

    public function removeChannel(Channel $channel): void
    {
        $this->channels->removeElement($channel);
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
