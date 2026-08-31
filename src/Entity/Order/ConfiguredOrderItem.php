<?php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_configured_order_item')]
#[ORM\Index(name: 'IDX_CN_CONFIGURED_ORDER', columns: ['order_id'])]
#[ORM\Index(name: 'IDX_CN_CONFIGURATION_HASH', columns: ['configuration_hash'])]
class ConfiguredOrderItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'configuredItems')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(name: 'configurator_code', length: 100)]
    private string $configuratorCode;

    #[ORM\Column(name: 'configurator_name')]
    private string $configuratorName;

    #[ORM\Column(name: 'locale_code', length: 20)]
    private string $localeCode;

    #[ORM\Column(name: 'channel_code', length: 64)]
    private string $channelCode;

    #[ORM\Column(name: 'currency_code', length: 3)]
    private string $currencyCode;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column(name: 'lead_time_code', length: 100, nullable: true)]
    private ?string $leadTimeCode = null;

    #[ORM\Column(name: 'lead_time_name', nullable: true)]
    private ?string $leadTimeName = null;

    #[ORM\Column(name: 'working_days', nullable: true)]
    private ?int $workingDays = null;

    #[ORM\Column(name: 'tax_category_code', length: 255, nullable: true)]
    private ?string $taxCategoryCode = null;

    #[ORM\Column(name: 'shipping_required', options: ['default' => true])]
    private bool $shippingRequired = true;

    #[ORM\Column(name: 'configuration_hash', length: 64)]
    private string $configurationHash;

/** @var array<string, mixed> */ #[ORM\Column(name: 'selections_snapshot', type: 'json')]
    private array $selectionsSnapshot;

/** @var array<string, mixed> */ #[ORM\Column(name: 'price_breakdown_snapshot', type: 'json')]
    private array $priceBreakdownSnapshot;

/** @var array<string, mixed> */ #[ORM\Column(name: 'canonical_configuration', type: 'json')]
    private array $canonicalConfiguration;

    #[ORM\Column(name: 'base_unit_amount', type: 'bigint')]
    // @phpstan-ignore doctrine.columnType
    private int $baseUnitAmount;

    #[ORM\Column(name: 'options_unit_amount', type: 'bigint')]
    // @phpstan-ignore doctrine.columnType
    private int $optionsUnitAmount;

    #[ORM\Column(name: 'unit_amount', type: 'bigint')]
    // @phpstan-ignore doctrine.columnType
    private int $unitAmount;

    #[ORM\Column(name: 'unit_total', type: 'bigint')]
    // @phpstan-ignore doctrine.columnType
    private int $unitTotal;

    #[ORM\Column(name: 'fixed_total', type: 'bigint')]
    // @phpstan-ignore doctrine.columnType
    private int $fixedTotal;

    #[ORM\Column(name: 'percentage_total', type: 'bigint')]
    // @phpstan-ignore doctrine.columnType
    private int $percentageTotal;

    #[ORM\Column(type: 'bigint')]
    // @phpstan-ignore doctrine.columnType
    private int $total;

    #[ORM\Column(name: 'snapshot_version')]
    private int $snapshotVersion = 1;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $selections
     * @param array<string, mixed> $breakdown
     * @param array<string, mixed> $canonical
     */
    public function __construct(string $configuratorCode, string $configuratorName, string $localeCode, string $channelCode, string $currencyCode, int $quantity, string $configurationHash, array $selections, array $breakdown, array $canonical, int $baseUnitAmount, int $optionsUnitAmount, int $unitAmount, int $unitTotal, int $fixedTotal, int $percentageTotal, int $total, ?string $leadTimeCode = null, ?string $leadTimeName = null, ?int $workingDays = null, ?string $taxCategoryCode = null, bool $shippingRequired = true)
    {
        $this->configuratorCode = $configuratorCode;
        $this->configuratorName = $configuratorName;
        $this->localeCode = $localeCode;
        $this->channelCode = $channelCode;
        $this->currencyCode = strtoupper($currencyCode);
        $this->quantity = $quantity;
        $this->configurationHash = $configurationHash;
        $this->selectionsSnapshot = $selections;
        $this->priceBreakdownSnapshot = $breakdown;
        $this->canonicalConfiguration = $canonical;
        $this->baseUnitAmount = $baseUnitAmount;
        $this->optionsUnitAmount = $optionsUnitAmount;
        $this->unitAmount = $unitAmount;
        $this->unitTotal = $unitTotal;
        $this->fixedTotal = $fixedTotal;
        $this->percentageTotal = $percentageTotal;
        $this->total = $total;
        $this->leadTimeCode = $leadTimeCode;
        $this->leadTimeName = $leadTimeName;
        $this->workingDays = $workingDays;
        $this->taxCategoryCode = $taxCategoryCode;
        $this->shippingRequired = $shippingRequired;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getConfiguratorCode(): string
    {
        return $this->configuratorCode;
    }

    public function getConfiguratorName(): string
    {
        return $this->configuratorName;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function getChannelCode(): string
    {
        return $this->channelCode;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getLeadTimeCode(): ?string
    {
        return $this->leadTimeCode;
    }

    public function getLeadTimeName(): ?string
    {
        return $this->leadTimeName;
    }

    public function getWorkingDays(): ?int
    {
        return $this->workingDays;
    }

    public function getTaxCategoryCode(): ?string
    {
        return $this->taxCategoryCode;
    }

    public function isShippingRequired(): bool
    {
        return $this->shippingRequired;
    }

    public function getConfigurationHash(): string
    {
        return $this->configurationHash;
    }

    /** @return array<string,mixed> */
    public function getSelectionsSnapshot(): array
    {
        return $this->selectionsSnapshot;
    }

    /** @return array<string,mixed> */
    public function getPriceBreakdownSnapshot(): array
    {
        return $this->priceBreakdownSnapshot;
    }

    /** @return array<string,mixed> */
    public function getCanonicalConfiguration(): array
    {
        return $this->canonicalConfiguration;
    }

    public function getBaseUnitAmount(): int
    {
        return $this->baseUnitAmount;
    }

    public function getOptionsUnitAmount(): int
    {
        return $this->optionsUnitAmount;
    }

    public function getUnitAmount(): int
    {
        return $this->unitAmount;
    }

    public function getUnitTotal(): int
    {
        return $this->unitTotal;
    }

    public function getFixedTotal(): int
    {
        return $this->fixedTotal;
    }

    public function getPercentageTotal(): int
    {
        return $this->percentageTotal;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getSnapshotVersion(): int
    {
        return $this->snapshotVersion;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function replacePricing(self $fresh): void
    {
        $this->quantity = $fresh->quantity;
        $this->configurationHash = $fresh->configurationHash;
        $this->selectionsSnapshot = $fresh->selectionsSnapshot;
        $this->priceBreakdownSnapshot = $fresh->priceBreakdownSnapshot;
        $this->canonicalConfiguration = $fresh->canonicalConfiguration;
        $this->baseUnitAmount = $fresh->baseUnitAmount;
        $this->optionsUnitAmount = $fresh->optionsUnitAmount;
        $this->unitAmount = $fresh->unitAmount;
        $this->unitTotal = $fresh->unitTotal;
        $this->fixedTotal = $fresh->fixedTotal;
        $this->percentageTotal = $fresh->percentageTotal;
        $this->total = $fresh->total;
        $this->leadTimeCode = $fresh->leadTimeCode;
        $this->leadTimeName = $fresh->leadTimeName;
        $this->workingDays = $fresh->workingDays;
        $this->taxCategoryCode = $fresh->taxCategoryCode;
        $this->shippingRequired = $fresh->shippingRequired;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
