<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_configurator_value_translation')]
#[ORM\Index(name: 'IDX_CNCVT_VALUE', columns: ['value_id'])]
#[ORM\UniqueConstraint(name: 'uniq_cn_cfg_value_locale', columns: ['value_id', 'locale'])]
class ConfiguratorValueTranslation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ConfiguratorValue::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'value_id', nullable: false, onDelete: 'CASCADE')]
    private ConfiguratorValue $value;

    #[ORM\Column(name: 'locale', length: 20)]
    private string $locale;

    #[ORM\Column(name: 'name', length: 255, nullable: false)]
    private string $name;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    public function __construct(string $locale, string $name)
    {
        $this->locale = $locale;
        $this->name = trim($name);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setValue(ConfiguratorValue $value): void
    {
        $this->value = $value;
    }

    public function getValue(): ConfiguratorValue
    {
        return $this->value;
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
}
