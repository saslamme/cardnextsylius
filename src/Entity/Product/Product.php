<?php

declare(strict_types=1);

namespace App\Entity\Product;

use App\Entity\Configurator\Configurator;
use App\Enum\Product\ProductKind;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Product as BaseProduct;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Sylius\MolliePlugin\Entity\ProductInterface;
use Sylius\MolliePlugin\Entity\ProductTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product')]
class Product extends BaseProduct implements ProductInterface
{
    use ProductTrait;

    #[ORM\ManyToOne(targetEntity: Manufacturer::class)]
    #[ORM\JoinColumn(name: 'manufacturer_id', nullable: true, onDelete: 'SET NULL')]
    private ?Manufacturer $manufacturer = null;

    #[ORM\Column(name: 'model', length: 255, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(name: 'data_quality_status', length: 32, options: ['default' => 'imported'])]
    private string $dataQualityStatus = 'imported';

    #[ORM\Column(name: 'homepage_featured', options: ['default' => false])]
    private bool $homepageFeatured = false;

    #[ORM\Column(name: 'homepage_position', options: ['default' => 100])]
    private int $homepagePosition = 100;

    #[ORM\Column(name: 'product_kind', length: 20, enumType: ProductKind::class, options: ['default' => 'standard'])]
    private ProductKind $productKind = ProductKind::STANDARD;

    #[ORM\OneToOne(mappedBy: 'product', targetEntity: Configurator::class, cascade: ['persist'])]
    private ?Configurator $configurator = null;

    /** @var Collection<int, ProductCompatibility> */
    #[ORM\OneToMany(mappedBy: 'sourceProduct', targetEntity: ProductCompatibility::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $compatibilities;

    /** @var Collection<int, ProductCompatibility> */
    #[ORM\OneToMany(mappedBy: 'targetProduct', targetEntity: ProductCompatibility::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $reverseCompatibilities;

    /** @var Collection<int, ProductDeviceCompatibility> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductDeviceCompatibility::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $deviceCompatibilities;

    /** @var Collection<int, ProductDocument> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductDocument::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'title' => 'ASC'])]
    private Collection $documents;

    public function __construct()
    {
        parent::__construct();
        $this->documents = new ArrayCollection();
        $this->compatibilities = new ArrayCollection();
        $this->reverseCompatibilities = new ArrayCollection();
        $this->deviceCompatibilities = new ArrayCollection();
    }

    public function getProductKind(): ProductKind
    {
        return $this->productKind;
    }

    public function setProductKind(ProductKind $productKind): void
    {
        if ($this->getId() !== null && $this->productKind !== $productKind) {
            throw new \DomainException('Der Produkttyp kann nach der Erstellung nicht mehr geändert werden.');
        }
        if ($productKind === ProductKind::STANDARD && $this->configurator !== null) {
            throw new \DomainException('Ein Produkt mit Konfigurator kann nicht als Standardprodukt markiert werden.');
        }

        $this->productKind = $productKind;
    }

    public function isConfigurable(): bool
    {
        return $this->productKind === ProductKind::CONFIGURABLE;
    }

    public function isStandard(): bool
    {
        return $this->productKind === ProductKind::STANDARD;
    }

    public function getConfigurator(): ?Configurator
    {
        return $this->configurator;
    }

    public function attachConfigurator(Configurator $configurator): void
    {
        if (!$this->isConfigurable()) {
            throw new \DomainException('Nur Konfigurationsprodukte dürfen einen Konfigurator besitzen.');
        }
        if ($this->configurator !== null && $this->configurator !== $configurator) {
            throw new \DomainException('Das Produkt besitzt bereits einen Konfigurator.');
        }

        $this->configurator = $configurator;
        if ($configurator->getProduct() !== $this) {
            $configurator->assignToProduct($this);
        }
    }

    public function getManufacturer(): ?Manufacturer
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?Manufacturer $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): void
    {
        $model = $model !== null ? trim($model) : null;
        $this->model = $model !== '' ? $model : null;
    }

    public function getDataQualityStatus(): string
    {
        return $this->dataQualityStatus;
    }

    public function setDataQualityStatus(string $dataQualityStatus): void
    {
        if (!in_array($dataQualityStatus, ['imported', 'needs_review', 'verified'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid data quality status "%s".', $dataQualityStatus));
        }

        $this->dataQualityStatus = $dataQualityStatus;
    }

    public function isHomepageFeatured(): bool
    {
        return $this->homepageFeatured;
    }

    public function setHomepageFeatured(bool $homepageFeatured): void
    {
        $this->homepageFeatured = $homepageFeatured;
    }

    public function getHomepagePosition(): int
    {
        return $this->homepagePosition;
    }

    public function setHomepagePosition(int $homepagePosition): void
    {
        $this->homepagePosition = max(0, $homepagePosition);
    }

    /** @return Collection<int, ProductDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(ProductDocument $document): void
    {
        if ($this->documents->contains($document)) {
            return;
        }

        $this->documents->add($document);
        $document->setProduct($this);
    }

    public function removeDocument(ProductDocument $document): void
    {
        $this->documents->removeElement($document);
    }

    /** @return list<ProductDocument> */
    public function getPublicDocuments(?string $localeCode = null): array
    {
        $documents = array_values(array_filter(
            $this->documents->toArray(),
            static fn (ProductDocument $document): bool => $document->isEnabled() && $document->getFilePath() !== null && ($document->getLocale() === null || $document->getLocale() === $localeCode),
        ));

        usort($documents, static fn (ProductDocument $a, ProductDocument $b): int => [$a->getPosition(), $a->getTitle()] <=> [$b->getPosition(), $b->getTitle()]);

        return $documents;
    }

    /** @return Collection<int, ProductCompatibility> */
    public function getCompatibilities(): Collection
    {
        return $this->compatibilities;
    }

    /** @return Collection<int, ProductCompatibility> */
    public function getReverseCompatibilities(): Collection
    {
        return $this->reverseCompatibilities;
    }

    public function addCompatibility(ProductCompatibility $compatibility): void
    {
        if ($this->compatibilities->contains($compatibility)) {
            return;
        }

        $this->compatibilities->add($compatibility);
        $compatibility->setSourceProduct($this);
    }

    public function removeCompatibility(ProductCompatibility $compatibility): void
    {
        $this->compatibilities->removeElement($compatibility);
    }

    public function addReverseCompatibility(ProductCompatibility $compatibility): void
    {
        if ($this->reverseCompatibilities->contains($compatibility)) {
            return;
        }

        $this->reverseCompatibilities->add($compatibility);
        $compatibility->setTargetProduct($this);
    }

    public function removeReverseCompatibility(ProductCompatibility $compatibility): void
    {
        $this->reverseCompatibilities->removeElement($compatibility);
    }

    /** @return Collection<int, ProductDeviceCompatibility> */
    public function getDeviceCompatibilities(): Collection
    {
        return $this->deviceCompatibilities;
    }

    public function addDeviceCompatibility(ProductDeviceCompatibility $compatibility): void
    {
        if (!$this->deviceCompatibilities->contains($compatibility)) {
            $this->deviceCompatibilities->add($compatibility);
            $compatibility->setProduct($this);
        }
    }

    public function removeDeviceCompatibility(ProductDeviceCompatibility $compatibility): void
    {
        $this->deviceCompatibilities->removeElement($compatibility);
    }

    /** @return list<ProductCompatibility> */
    public function getPublicCompatibilities(): array
    {
        return $this->sortPublicCompatibilities($this->compatibilities);
    }

    /** @return list<ProductCompatibility> */
    public function getPublicReverseCompatibilities(): array
    {
        return $this->sortPublicCompatibilities($this->reverseCompatibilities);
    }

    /** @param Collection<int, ProductCompatibility> $collection @return list<ProductCompatibility> */
    private function sortPublicCompatibilities(Collection $collection): array
    {
        $items = array_values(array_filter(
            $collection->toArray(),
            static fn (ProductCompatibility $compatibility): bool => $compatibility->isEnabled() && $compatibility->getSourceProduct()->isEnabled() && $compatibility->getTargetProduct()->isEnabled(),
        ));

        usort($items, static fn (ProductCompatibility $a, ProductCompatibility $b): int => [$a->getPosition(), $a->getId() ?? 0] <=> [$b->getPosition(), $b->getId() ?? 0]);

        return $items;
    }

    protected function createTranslation(): ProductTranslationInterface
    {
        return new ProductTranslation();
    }
}
