<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use App\Entity\Channel\Channel;
use App\Repository\Configurator\ConfiguratorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfiguratorRepository::class)]
#[ORM\Table(name: 'cardnext_configurator')]
class Configurator
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'code', length: 100, unique: true)]
    private string $code;

    #[ORM\Column(name: 'name', length: 255)]
    private string $internalName;

    #[ORM\Column(name: 'enabled', options: ['default' => true])]
    private bool $enabled = true;

    /** @var Collection<int, ConfiguratorTranslation> */
    #[ORM\OneToMany(mappedBy: 'configurator', targetEntity: ConfiguratorTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    /** @var Collection<int, Channel> */
    #[ORM\ManyToMany(targetEntity: Channel::class)]
    #[ORM\JoinTable(name: 'cardnext_configurator_channel')]
    private Collection $channels;

    /** @var Collection<int, ConfiguratorImage> */
    #[ORM\OneToMany(mappedBy: 'configurator', targetEntity: ConfiguratorImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    /** @var Collection<int, ConfiguratorTaxon> */
    #[ORM\OneToMany(mappedBy: 'configurator', targetEntity: ConfiguratorTaxon::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $taxonAssignments;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ConfiguratorSection> */
    #[ORM\OneToMany(mappedBy: 'configurator', targetEntity: ConfiguratorSection::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $sections;

    /** @var Collection<int, ConfiguratorDependency> */
    #[ORM\OneToMany(mappedBy: 'configurator', targetEntity: ConfiguratorDependency::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['priority' => 'ASC', 'id' => 'ASC'])]
    private Collection $dependencies;

    /** @var Collection<int, ConfiguratorLeadTime> */
    #[ORM\OneToMany(mappedBy: 'configurator', targetEntity: ConfiguratorLeadTime::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $leadTimes;

    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->internalName = $name;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
        $this->sections = new ArrayCollection();
        $this->dependencies = new ArrayCollection();
        $this->leadTimes = new ArrayCollection();
        $this->translations = new ArrayCollection();
        $this->channels = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->taxonAssignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->internalName;
    }

    public function setName(string $name): void
    {
        $this->internalName = $name;
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

    /** @return Collection<int, ConfiguratorTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(ConfiguratorTranslation $translation): void
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setConfigurator($this);
            $this->touch();
        }
    }

    public function getTranslation(string $locale): ?ConfiguratorTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

return null;
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
            $this->touch();
        }
    }

    public function removeChannel(Channel $channel): void
    {
        $this->channels->removeElement($channel);
    }

    public function hasChannel(Channel $channel): bool
    {
        return $this->channels->contains($channel);
    }

    /** @return Collection<int, ConfiguratorImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ConfiguratorImage $image): void
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setConfigurator($this);
        }
    }

    /** @return Collection<int, ConfiguratorTaxon> */
    public function getTaxonAssignments(): Collection
    {
        return $this->taxonAssignments;
    }

    public function addTaxonAssignment(ConfiguratorTaxon $assignment): void
    {
        if (!$this->taxonAssignments->contains($assignment)) {
            $this->taxonAssignments->add($assignment);
            $assignment->setConfigurator($this);
        }
    }

    /** @return Collection<int, ConfiguratorSection> */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(ConfiguratorSection $section): void
    {
        if (!$this->sections->contains($section)) {
            if ($section->hasConfigurator() && $section->getConfigurator() !== $this) {
                throw new \DomainException('Section already belongs to another configurator.');
            }
            foreach ($this->sections as $existing) {
                if ($existing->getCode() === $section->getCode()) {
                    throw new \DomainException(sprintf('Section code "%s" must be unique within configurator "%s".', $section->getCode(), $this->code));
                }
            }
            $codes = [];
            foreach ($section->getFields() as $field) {
                if (isset($codes[$field->getCode()])) {
                    throw new \DomainException(sprintf('Field code "%s" must be unique within configurator "%s".', $field->getCode(), $this->code));
                }
                $codes[$field->getCode()] = true;
                $this->assertUniqueFieldCode($field);
            }
            $this->sections->add($section);
            $section->setConfigurator($this);
            $this->touch();
        }
    }

    public function assertUniqueFieldCode(ConfiguratorField $candidate): void
    {
        foreach ($this->sections as $section) {
            foreach ($section->getFields() as $field) {
                if ($field !== $candidate && $field->getCode() === $candidate->getCode()) {
                    throw new \DomainException(sprintf('Field code "%s" must be unique within configurator "%s".', $candidate->getCode(), $this->code));
                }
            }
        }
    }

    /** @return Collection<int, ConfiguratorDependency> */
    public function getDependencies(): Collection
    {
        return $this->dependencies;
    }

    public function addDependency(ConfiguratorDependency $dependency): void
    {
        if ($dependency->getConfigurator() !== $this) {
            throw new \DomainException('Dependency belongs to another configurator.');
        }
        if (!$this->dependencies->contains($dependency)) {
            $this->dependencies->add($dependency);
        }
    }

    /** @return Collection<int, ConfiguratorLeadTime> */
    public function getLeadTimes(): Collection
    {
        return $this->leadTimes;
    }

    public function addLeadTime(ConfiguratorLeadTime $leadTime): void
    {
        if ($leadTime->getConfigurator() !== $this) {
            throw new \DomainException('Lead time belongs to another configurator.');
        }
        foreach ($this->leadTimes as $existing) {
            if ($existing !== $leadTime && $existing->getCode() === $leadTime->getCode()) {
                throw new \DomainException(sprintf('Lead-time code "%s" must be unique within configurator "%s".', $leadTime->getCode(), $this->code));
            }
        }
        if (!$this->leadTimes->contains($leadTime)) {
            $this->leadTimes->add($leadTime);
            $this->touch();
        }
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $now = new \DateTimeImmutable();
        $this->updatedAt = $now > $this->updatedAt ? $now : $this->updatedAt->modify('+1 microsecond');
    }
}
