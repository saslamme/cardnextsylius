<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name:'cardnext_configurator_lead_time')] #[ORM\UniqueConstraint(name:'UNIQ_CN_CFG_LEAD_CODE', columns:['configurator_id', 'code'])]
#[ORM\Index(name:'IDX_CN_CFG_LEAD_PARENT', columns:['configurator_id', 'position', 'enabled'])]
class ConfiguratorLeadTime
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column(name:'id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity:Configurator::class, inversedBy:'leadTimes'),ORM\JoinColumn(name:'configurator_id', nullable:false, onDelete:'CASCADE')]
    private Configurator $configurator;

    #[ORM\Column(name:'code', length:100)]
    private string $code;

    #[ORM\Column(name:'name', length:255)]
    private string $name;

    #[ORM\Column(name:'description', type:'text', nullable:true)]
    private ?string $description = null;

    #[ORM\Column(name:'working_days')]
    private int $workingDays;

    #[ORM\Column(name:'position')]
    private int $position = 0;

    #[ORM\Column(name:'enabled', options:['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name: 'preselected', options: ['default' => false])]
    private bool $preselected = false;

    public function __construct(Configurator $c, string $code, string $name, int $days)
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Working days cannot be negative.');
        }
        $this->configurator = $c;
        $this->code = $code;
        $this->name = $name;
        $this->workingDays = $days;
        $c->addLeadTime($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfigurator(): Configurator
    {
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

    public function setName(string $value): void
    {
        $this->name = $value;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $value): void
    {
        $this->description = $value;
    }

    public function getWorkingDays(): int
    {
        return $this->workingDays;
    }

    public function setWorkingDays(int $value): void
    {
        if ($value < 0) {
            throw new \DomainException('Working days cannot be negative.');
        } $this->workingDays = $value;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $value): void
    {
        $this->position = $value;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $value): void
    {
        $this->enabled = $value;
        if (!$value) {
            $this->preselected = false;
        }
    }

    public function isPreselected(): bool
    {
        return $this->preselected;
    }

    public function setPreselected(bool $preselected): void
    {
        if ($preselected && !$this->enabled) {
            throw new \DomainException('A disabled lead time cannot be preselected.');
        }

        if ($preselected) {
            foreach ($this->configurator->getLeadTimes() as $sibling) {
                $sibling->preselected = false;
            }
        }

        $this->preselected = $preselected;
    }
}
