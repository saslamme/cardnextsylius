<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use App\Entity\Taxonomy\Taxon;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_configurator_taxon')]
#[ORM\UniqueConstraint(name: 'uniq_configurator_taxon', columns: ['configurator_id', 'taxon_id'])]
class ConfiguratorTaxon
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Configurator::class, inversedBy: 'taxonAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Configurator $configurator;

    #[ORM\ManyToOne(targetEntity: Taxon::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Taxon $taxon;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(name: 'is_primary', options: ['default' => false])]
    private bool $primary = false;

    public function __construct(Taxon $taxon, int $position = 0)
    {
        $this->taxon = $taxon;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setConfigurator(Configurator $c): void
    {
        $this->configurator = $c;
    }

    public function getConfigurator(): Configurator
    {
        return $this->configurator;
    }

    public function getTaxon(): Taxon
    {
        return $this->taxon;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function setPrimary(bool $primary): void
    {
        $this->primary = $primary;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }
}
