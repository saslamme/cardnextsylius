<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'cardnext_configurator_section')]
#[ORM\UniqueConstraint(name:'UNIQ_CN_CFG_SECTION_CODE', columns:['configurator_id', 'code'])]
#[ORM\Index(name:'IDX_CN_CFG_SECTION_PARENT', columns:['configurator_id', 'position', 'enabled'])]
class ConfiguratorSection
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column(name:'id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity:Configurator::class, inversedBy:'sections'),ORM\JoinColumn(name:'configurator_id', nullable:false, onDelete:'CASCADE')]
    private Configurator $configurator;

    #[ORM\Column(name:'code', length:100)]
    private string $code;

    #[ORM\Column(name:'name', length:255)]
    private string $name;

    #[ORM\Column(name:'description', type:'text', nullable:true)]
    private ?string $description = null;

    #[ORM\Column(name:'position')]
    private int $position = 0;

    #[ORM\Column(name:'enabled', options:['default' => true])]
    private bool $enabled = true;

/** @var Collection<int,ConfiguratorField> */ #[ORM\OneToMany(mappedBy:'section', targetEntity:ConfiguratorField::class, cascade:['persist'], orphanRemoval:true),ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $fields;

    /** @var Collection<int, ConfiguratorSectionTranslation> */
    #[ORM\OneToMany(mappedBy: 'section', targetEntity: ConfiguratorSectionTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct(string $code, string $name)
    {
        $this->translations = new ArrayCollection();
        $this->code = $code;
        $this->name = $name;
        $this->fields = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setConfigurator(Configurator $v): void
    {
        if (isset($this->configurator) && $this->configurator !== $v) {
            throw new \DomainException('Section already belongs to another configurator.');
        }
        $this->configurator = $v;
        foreach ($this->fields as $field) {
            $field->setSection($this);
        }
    }

    public function getConfigurator(): Configurator
    {
        if (!isset($this->configurator)) {
            throw new \DomainException('Section is not attached to a configurator.');
        }

        return $this->configurator;
    }

    public function hasConfigurator(): bool
    {
        return isset($this->configurator);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $v): void
    {
        $this->description = $v;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $v): void
    {
        $this->position = $v;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $v): void
    {
        $this->enabled = $v;
    }

    /** @return Collection<int,ConfiguratorField> */
    public function getFields(): Collection
    {
        return $this->fields;
    }

    public function addField(ConfiguratorField $v): void
    {
        if (!$this->fields->contains($v)) {
            if ($v->hasSection() && $v->getSection() !== $this) {
                throw new \DomainException('Field already belongs to another section.');
            }
            foreach ($this->fields as $existing) {
                if ($existing->getCode() === $v->getCode()) {
                    throw new \DomainException(sprintf('Field code "%s" must be unique within section "%s".', $v->getCode(), $this->code));
                }
            }
            if (isset($this->configurator)) {
                $this->configurator->assertUniqueFieldCode($v);
            }
            $this->fields->add($v);
            $v->setSection($this);
        }
    }

    /** @return Collection<int, ConfiguratorSectionTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(ConfiguratorSectionTranslation $translation): void
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setSection($this);
        }
    }

    public function removeTranslation(ConfiguratorSectionTranslation $translation): void
    {
        $this->translations->removeElement($translation);
    }

    public function getTranslation(string $locale): ?ConfiguratorSectionTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }
}
