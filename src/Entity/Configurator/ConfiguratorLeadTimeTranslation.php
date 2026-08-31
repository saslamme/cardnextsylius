<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_configurator_lead_time_translation')]
#[ORM\Index(name: 'IDX_CNCLTT_LEAD', columns: ['lead_time_id'])]
#[ORM\UniqueConstraint(name: 'uniq_cn_cfg_lead_time_locale', columns: ['lead_time_id', 'locale'])]
class ConfiguratorLeadTimeTranslation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ConfiguratorLeadTime::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'lead_time_id', nullable: false, onDelete: 'CASCADE')]
    private ConfiguratorLeadTime $leadTime;

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

    public function setLeadTime(ConfiguratorLeadTime $leadTime): void
    {
        $this->leadTime = $leadTime;
    }

    public function getLeadTime(): ConfiguratorLeadTime
    {
        return $this->leadTime;
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
