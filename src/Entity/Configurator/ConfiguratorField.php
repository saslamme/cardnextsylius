<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use App\Enum\Configurator\FieldType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name:'cardnext_configurator_field')] #[ORM\UniqueConstraint(name:'UNIQ_CN_CFG_FIELD_CODE', columns:['configurator_id', 'code'])]
#[ORM\Index(name:'IDX_CN_CFG_FIELD_CONFIGURATOR', columns:['configurator_id'])]
#[ORM\Index(name:'IDX_CN_CFG_FIELD_PARENT', columns:['section_id', 'position', 'enabled'])]
class ConfiguratorField
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column(name:'id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity:ConfiguratorSection::class, inversedBy:'fields'),ORM\JoinColumn(name:'section_id', nullable:false, onDelete:'CASCADE')]
    private ConfiguratorSection $section;

    #[ORM\ManyToOne(targetEntity: Configurator::class), ORM\JoinColumn(name: 'configurator_id', nullable: false, onDelete: 'CASCADE')]
    private Configurator $configurator;

    #[ORM\Column(name:'code', length:100)]
    private string $code;

    #[ORM\Column(name:'name', length:255)]
    private string $name;

    #[ORM\Column(name:'description', type:'text', nullable:true)]
    private ?string $description = null;

    #[ORM\Column(name:'help_text', type:'text', nullable:true)]
    private ?string $helpText = null;

    #[ORM\Column(name:'type', enumType:FieldType::class, length:30)]
    private FieldType $type;

    #[ORM\Column(name:'required', options:['default' => false])]
    private bool $required = false;

    #[ORM\Column(name:'position')]
    private int $position = 0;

    #[ORM\Column(name:'enabled', options:['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name:'minimum_value', type:'string', length:64, nullable:true)]
    private ?string $minimumValue = null;

    #[ORM\Column(name:'maximum_value', type:'string', length:64, nullable:true)]
    private ?string $maximumValue = null;

    #[ORM\Column(name:'step', type:'string', length:64, nullable:true)]
    private ?string $step = null;

/** @var Collection<int,ConfiguratorValue> */ #[ORM\OneToMany(mappedBy:'field', targetEntity:ConfiguratorValue::class, cascade:['persist'], orphanRemoval:true),ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $values;

    /** @var Collection<int, ConfiguratorFieldTranslation> */
    #[ORM\OneToMany(mappedBy: 'field', targetEntity: ConfiguratorFieldTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct(string $code, string $name, FieldType $type)
    {
        $this->translations = new ArrayCollection();
        $this->code = $code;
        $this->name = $name;
        $this->type = $type;
        $this->values = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setSection(ConfiguratorSection $v): void
    {
        if (isset($this->section) && $this->section !== $v) {
            throw new \DomainException('Field already belongs to another section.');
        }
        $this->section = $v;
        if ($v->hasConfigurator()) {
            $this->configurator = $v->getConfigurator();
        }
    }

    public function getSection(): ConfiguratorSection
    {
        if (!isset($this->section)) {
            throw new \DomainException('Field is not attached to a section.');
        }

        return $this->section;
    }

    public function hasSection(): bool
    {
        return isset($this->section);
    }

    public function getConfigurator(): Configurator
    {
        if (!isset($this->configurator)) {
            throw new \DomainException('Field is not attached to a configurator.');
        }

        return $this->configurator;
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

    public function setDescription(?string $value): void
    {
        $this->description = $value;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $value): void
    {
        $this->helpText = $value;
    }

    public function getType(): FieldType
    {
        return $this->type;
    }

    public function setType(FieldType $type): void
    {
        if (!in_array($type, [FieldType::INTEGER, FieldType::QUANTITY, FieldType::DECIMAL], true) &&
            ($this->minimumValue !== null || $this->maximumValue !== null || $this->step !== null)) {
            throw new \DomainException('Clear numeric constraints before changing to a non-numeric field type.');
        }
        $this->type = $type;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $v): void
    {
        $this->required = $v;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $v): void
    {
        $this->enabled = $v;
    }

    public function getMinimumValue(): ?string
    {
        return $this->minimumValue;
    }

    public function setMinimumValue(?string $v): void
    {
        $this->setNumericConfiguration($v, $this->maximumValue, $this->step);
    }

    public function getMaximumValue(): ?string
    {
        return $this->maximumValue;
    }

    public function setMaximumValue(?string $v): void
    {
        $this->setNumericConfiguration($this->minimumValue, $v, $this->step);
    }

    public function getStep(): ?string
    {
        return $this->step;
    }

    public function setStep(?string $v): void
    {
        $this->setNumericConfiguration($this->minimumValue, $this->maximumValue, $v);
    }

    public function setPosition(int $v): void
    {
        $this->position = $v;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /** @return Collection<int,ConfiguratorValue> */
    public function getValues(): Collection
    {
        return $this->values;
    }

    public function addValue(ConfiguratorValue $v): void
    {
        if (!$this->values->contains($v)) {
            if ($v->hasField() && $v->getField() !== $this) {
                throw new \DomainException('Value already belongs to another field.');
            }
            foreach ($this->values as $existing) {
                if ($existing->getCode() === $v->getCode()) {
                    throw new \DomainException(sprintf('Value code "%s" must be unique within field "%s".', $v->getCode(), $this->code));
                }
            }
            $this->values->add($v);
            $v->setField($this);
            if ($v->isPreselected()) {
                $v->setPreselected(true);
            }
        }
    }

    private function setNumericConfiguration(?string $minimum, ?string $maximum, ?string $step): void
    {
        if (!in_array($this->type, [FieldType::INTEGER, FieldType::QUANTITY, FieldType::DECIMAL], true)) {
            if ($minimum !== null || $maximum !== null || $step !== null) {
                throw new \DomainException('Numeric constraints are only valid for numeric fields.');
            }
        }
        $integer = in_array($this->type, [FieldType::INTEGER, FieldType::QUANTITY], true);
        foreach (['minimum' => $minimum, 'maximum' => $maximum, 'step' => $step] as $name => $value) {
            if ($value !== null && ($integer ? preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) !== 1 : !is_numeric($value))) {
                throw new \DomainException(sprintf('%s must be a valid %s number.', ucfirst($name), $integer ? 'integer' : 'decimal'));
            }
        }
        if ($step !== null && (float) $step <= 0) {
            throw new \DomainException('Step must be greater than zero.');
        }
        if ($minimum !== null && $maximum !== null && (float) $maximum < (float) $minimum) {
            throw new \DomainException('Maximum must be greater than or equal to minimum.');
        }
        $this->minimumValue = $minimum;
        $this->maximumValue = $maximum;
        $this->step = $step;
    }

    /** @return Collection<int, ConfiguratorFieldTranslation> */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(ConfiguratorFieldTranslation $translation): void
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setField($this);
        }
    }

    public function removeTranslation(ConfiguratorFieldTranslation $translation): void
    {
        $this->translations->removeElement($translation);
    }

    public function getTranslation(string $locale): ?ConfiguratorFieldTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }
}
