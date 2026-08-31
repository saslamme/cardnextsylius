<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_configurator_section_translation')]
#[ORM\Index(name: 'IDX_CNCST_SECTION', columns: ['section_id'])]
#[ORM\UniqueConstraint(name: 'uniq_cn_cfg_section_locale', columns: ['section_id', 'locale'])]
class ConfiguratorSectionTranslation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ConfiguratorSection::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'section_id', nullable: false, onDelete: 'CASCADE')]
    private ConfiguratorSection $section;

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

    public function setSection(ConfiguratorSection $section): void
    {
        $this->section = $section;
    }

    public function getSection(): ConfiguratorSection
    {
        return $this->section;
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
