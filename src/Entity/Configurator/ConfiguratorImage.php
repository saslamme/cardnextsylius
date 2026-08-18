<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_configurator_image')]
class ConfiguratorImage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Configurator::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Configurator $configurator;

    #[ORM\Column(length: 512)]
    private string $path;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(name: 'alt_text', length: 255, nullable: true)]
    private ?string $altText = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setConfigurator(Configurator $c): void
    {
        $this->configurator = $c;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
