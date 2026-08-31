<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_configurator_field_translation')]
#[ORM\Index(name: 'IDX_CNCFT_FIELD', columns: ['field_id'])]
#[ORM\UniqueConstraint(name: 'uniq_cn_cfg_field_locale', columns: ['field_id', 'locale'])]
class ConfiguratorFieldTranslation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ConfiguratorField::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'field_id', nullable: false, onDelete: 'CASCADE')]
    private ConfiguratorField $field;

    #[ORM\Column(name: 'locale', length: 20)]
    private string $locale;

    #[ORM\Column(name: 'name', length: 255, nullable: false)]
    private string $name;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'help_text', type: 'text', nullable: true)]
    private ?string $helpText = null;

    public function __construct(string $locale, string $name)
    {
        $this->locale = $locale;
        $this->name = trim($name);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setField(ConfiguratorField $field): void
    {
        $this->field = $field;
    }

    public function getField(): ConfiguratorField
    {
        return $this->field;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $value): void
    {
        $this->name = trim($value);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $value): void
    {
        $this->description = $value === null ? null : trim($value);
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $value): void
    {
        $this->helpText = $value === null ? null : trim($value);
    }
}
