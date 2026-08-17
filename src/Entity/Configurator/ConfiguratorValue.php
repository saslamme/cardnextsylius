<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use App\Repository\Configurator\ConfiguratorValueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass:ConfiguratorValueRepository::class)] #[ORM\Table(name:'cardnext_configurator_value')] #[ORM\UniqueConstraint(name:'UNIQ_CN_CFG_VALUE_CODE', columns:['field_id','code'])]
class ConfiguratorValue
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity:ConfiguratorField::class, inversedBy:'values'),ORM\JoinColumn(nullable:false, onDelete:'CASCADE')] private ConfiguratorField $field;
    #[ORM\Column(length:100)] private string $code;
    #[ORM\Column(length:255)] private string $name;
    #[ORM\Column(type:'text', nullable:true)] private ?string $description = null;
    #[ORM\Column] private int $position = 0;
    #[ORM\Column(options:['default' => true])] private bool $enabled = true;
    #[ORM\Column(length:7, nullable:true)] private ?string $colorHex = null;
    #[ORM\Column(length:500, nullable:true)] private ?string $imagePath = null;
    #[ORM\Column(length:100, nullable:true)] private ?string $icon = null;
    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setField(ConfiguratorField $v): void
    {
        $this->field = $v;
    }

    public function getField(): ConfiguratorField
    {
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $v): void
    {
        $this->enabled = $v;
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
}
