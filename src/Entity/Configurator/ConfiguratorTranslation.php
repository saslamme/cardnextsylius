<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_configurator_translation')]
#[ORM\UniqueConstraint(name: 'uniq_configurator_locale', columns: ['configurator_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_configurator_path_locale', columns: ['locale', 'path'])]
class ConfiguratorTranslation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Configurator::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Configurator $configurator;

    #[ORM\Column(length: 20)]
    private string $locale;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 512)]
    private string $path;

    #[ORM\Column(name: 'short_description', type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'meta_title', length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(name: 'meta_description', type: 'text', nullable: true)]
    private ?string $metaDescription = null;

    public function __construct(string $locale, string $name, string $path)
    {
        $this->locale = $locale;
        $this->name = $name;
        $this->setPath($path);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setConfigurator(Configurator $configurator): void
    {
        $this->configurator = $configurator;
    }

    public function getConfigurator(): Configurator
    {
        return $this->configurator;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $path = trim($path, " \t\n\r\0\x0B/");
        if ($path === '' || str_contains($path, '://') || str_contains($path, '?') || str_contains($path, '#') || str_contains($path, '..') || str_contains($path, '//') || preg_match('~^[\\pL\\pN](?:[\\pL\\pN_-]*[\\pL\\pN])?(?:/[\\pL\\pN](?:[\\pL\\pN_-]*[\\pL\\pN])?)*$~u', $path) !== 1) {
            throw new \InvalidArgumentException('Invalid configurator path.');
        } $this->path = $path;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $value): void
    {
        $this->shortDescription = self::nullable($value);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $value): void
    {
        $this->description = self::nullable($value);
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $value): void
    {
        $this->metaTitle = self::nullable($value);
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $value): void
    {
        $this->metaDescription = self::nullable($value);
    }

    private static function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
