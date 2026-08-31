<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use App\Enum\Configurator\FieldType;
use App\Repository\Configurator\ConfiguratorValueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass:ConfiguratorValueRepository::class)] #[ORM\Table(name:'cardnext_configurator_value')] #[ORM\UniqueConstraint(name:'UNIQ_CN_CFG_VALUE_CODE', columns:['field_id', 'code'])]
#[ORM\Index(name:'IDX_CN_CFG_VALUE_PARENT', columns:['field_id', 'position', 'enabled'])]
class ConfiguratorValue
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column(name:'id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity:ConfiguratorField::class, inversedBy:'values'),ORM\JoinColumn(name:'field_id', nullable:false, onDelete:'CASCADE')]
    private ConfiguratorField $field;

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

    #[ORM\Column(name:'preselected', options:['default' => false])]
    private bool $preselected = false;

    #[ORM\Column(name:'color_hex', length:7, nullable:true)]
    private ?string $colorHex = null;

    #[ORM\Column(name:'image_path', length:500, nullable:true)]
    private ?string $imagePath = null;

    #[ORM\Column(name:'icon', length:100, nullable:true)]
    private ?string $icon = null;

    /** @var Collection<int, ConfiguratorValueTranslation> */
    #[ORM\OneToMany(mappedBy: 'value', targetEntity: ConfiguratorValueTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct(string $code, string $name)
    {
        $this->translations = new ArrayCollection();
        $this->code = $code;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setField(ConfiguratorField $v): void
    {
        if (isset($this->field) && $this->field !== $v) {
            throw new \DomainException('Value already belongs to another field.');
        }
        $this->field = $v;
    }

    public function hasField(): bool
    {
        return isset($this->field);
    }

    public function getField(): ConfiguratorField
    {
        if (!isset($this->field)) {
            throw new \DomainException('Value is not attached to a field.');
        }

        return $this->field;
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

    public function getColorHex(): ?string
    {
        return $this->colorHex;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $v): void
    {
        $this->enabled = $v;
        if (!$v) {
            $this->preselected = false;
        }
    }

    public function isPreselected(): bool
    {
        return $this->preselected;
    }

    public function setPreselected(bool $preselected): void
    {
        $this->preselected = $preselected && $this->enabled;

        if (!$this->preselected || !$this->hasField() || $this->field->getType() !== FieldType::SINGLE_CHOICE) {
            return;
        }

        foreach ($this->field->getValues() as $sibling) {
            if ($sibling !== $this) {
                $sibling->preselected = false;
            }
        }
    }

    public function setPosition(int $v): void
    {
        $this->position = $v;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setDescription(?string $v): void
    {
        $this->description = $v;
    }

    public function setColorHex(?string $v): void
    {
        $this->colorHex = $v;
    }

    public function setImagePath(?string $v): void
    {
        $this->imagePath = $v;
    }

    public function setIcon(?string $v): void
    {
        $this->icon = $v;
    }

    /** @return Collection<int, ConfiguratorValueTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(ConfiguratorValueTranslation $translation): void
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setValue($this);
        }
    }

    public function removeTranslation(ConfiguratorValueTranslation $translation): void
    {
        $this->translations->removeElement($translation);
    }

    public function getTranslation(string $locale): ?ConfiguratorValueTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }
}
