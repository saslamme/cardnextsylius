<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use App\Entity\Product\Product;
use App\Repository\Configurator\ConfiguratorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfiguratorRepository::class)]
#[ORM\Table(name: 'cardnext_configurator')]
class Configurator
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 100, unique: true)]
    private string $code;
    #[ORM\Column(length: 255)]
    private string $name;
    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Product $product = null;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;
    /** @var Collection<int, ConfiguratorSection> */
    #[ORM\OneToMany(mappedBy: 'configurator', targetEntity: ConfiguratorSection::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $sections;
    /** @var Collection<int, ConfiguratorDependency> */
    #[ORM\OneToMany(mappedBy: 'configurator', targetEntity: ConfiguratorDependency::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['priority' => 'ASC', 'id' => 'ASC'])]
    private Collection $dependencies;

    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
        $this->sections = new ArrayCollection();
        $this->dependencies = new ArrayCollection();
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
        return $this->name;
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
    public function getProduct(): ?Product
    {
        return $this->product;
    }
    public function setProduct(?Product $product): void
    {
        $this->product = $product;
        $this->touch();
    }
    /** @return Collection<int, ConfiguratorSection> */ public function getSections(): Collection
    {
        return $this->sections;
    }
    public function addSection(ConfiguratorSection $section): void
    {
        if (!$this->sections->contains($section)) {
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
        $this->updatedAt = new \DateTimeImmutable();
    }
}
