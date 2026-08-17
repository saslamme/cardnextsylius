<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use Doctrine\Common\Collections\{ArrayCollection,Collection};
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'cardnext_configurator_section')]
#[ORM\UniqueConstraint(name:'UNIQ_CN_CFG_SECTION_CODE', columns:['configurator_id','code'])]
class ConfiguratorSection
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity:Configurator::class, inversedBy:'sections'),ORM\JoinColumn(nullable:false, onDelete:'CASCADE')] private Configurator $configurator;
    #[ORM\Column(length:100)] private string $code;
    #[ORM\Column(length:255)] private string $name;
    #[ORM\Column(type:'text', nullable:true)] private ?string $description = null;
    #[ORM\Column] private int $position = 0;
    #[ORM\Column(options:['default' => true])] private bool $enabled = true;
    /** @var Collection<int,ConfiguratorField> */ #[ORM\OneToMany(mappedBy:'section', targetEntity:ConfiguratorField::class, cascade:['persist'], orphanRemoval:true),ORM\OrderBy(['position' => 'ASC','id' => 'ASC'])] private Collection $fields;
    public function __construct(string $code, string $name)
    {
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
        $this->configurator = $v;
        foreach ($this->fields as $field) {
            $field->setSection($this);
        }
    }

    public function getConfigurator(): Configurator
    {
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

    /** @return Collection<int,ConfiguratorField> */ public function getFields(): Collection
    {
        return $this->fields;
    }

    public function addField(ConfiguratorField $v): void
    {
        if (!$this->fields->contains($v)) {
            if (isset($this->configurator)) {
                $this->configurator->assertUniqueFieldCode($v);
            }
            $this->fields->add($v);
            $v->setSection($this);
        }
    }
}
