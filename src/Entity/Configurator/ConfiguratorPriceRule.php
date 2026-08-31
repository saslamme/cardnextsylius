<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use App\Entity\Channel\Channel;
use App\Enum\Configurator\FieldType;
use App\Enum\Configurator\MultiplierType;
use App\Enum\Configurator\PercentageBase;
use App\Enum\Configurator\PriceType;
use App\Repository\Configurator\ConfiguratorPriceRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass:ConfiguratorPriceRuleRepository::class)] #[ORM\Table(name:'cardnext_configurator_price_rule')] #[ORM\Index(name:'IDX_CN_CFG_RULE_LOOKUP', columns:['configurator_id', 'value_id', 'channel_id', 'currency_code', 'minimum_quantity', 'maximum_quantity', 'enabled'])] #[ORM\Index(name:'IDX_CN_CFG_RULE_LEAD', columns:['lead_time_id'])]
class ConfiguratorPriceRule
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column(name:'id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity:Configurator::class),ORM\JoinColumn(name:'configurator_id', nullable:false, onDelete:'CASCADE')]
    private Configurator $configurator;

    #[ORM\ManyToOne(targetEntity:ConfiguratorValue::class),ORM\JoinColumn(name:'value_id', nullable:true, onDelete:'CASCADE')]
    private ?ConfiguratorValue $value = null;

    #[ORM\ManyToOne(targetEntity:ConfiguratorLeadTime::class),ORM\JoinColumn(name:'lead_time_id', nullable:true, onDelete:'CASCADE')]
    private ?ConfiguratorLeadTime $leadTime = null;

    #[ORM\ManyToOne(targetEntity:Channel::class),ORM\JoinColumn(name:'channel_id', nullable:true, onDelete:'CASCADE')]
    private ?Channel $channel = null;

    #[ORM\Column(name:'currency_code', length:3)]
    private string $currencyCode;

    #[ORM\Column(name:'charge_code', length:100)]
    private string $chargeCode;

    #[ORM\Column(name:'label', length:255, nullable:true)]
    private ?string $label = null;

    #[ORM\Column(name:'minimum_quantity')]
    private int $minimumQuantity = 1;

    #[ORM\Column(name:'maximum_quantity', nullable:true)]
    private ?int $maximumQuantity = null;

    #[ORM\Column(name:'price_type', enumType:PriceType::class, length:20)]
    private PriceType $priceType;

/** Minor units for UNIT/FIXED; basis points for PERCENT. */ #[ORM\Column(name:'amount', type:'bigint')]
    // @phpstan-ignore doctrine.columnType
    private int $amount;

    #[ORM\Column(name:'multiplier_type', enumType:MultiplierType::class, length:20)]
    private MultiplierType $multiplierType = MultiplierType::NONE;

    #[ORM\ManyToOne(targetEntity:ConfiguratorField::class),ORM\JoinColumn(name:'multiplier_field_id', nullable:true, onDelete:'CASCADE')]
    private ?ConfiguratorField $multiplierField = null;

    #[ORM\Column(name:'percentage_base', enumType:PercentageBase::class, length:20, nullable:true)]
    private ?PercentageBase $percentageBase = null;

    #[ORM\Column(name:'priority')]
    private int $priority = 0;

    #[ORM\Column(name:'enabled', options:['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name:'created_at', type:'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name:'updated_at', type:'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Configurator $c, string $currency, string $charge, PriceType $type, int $amount)
    {
        if (preg_match('/^[A-Za-z]{3}$/D', $currency) !== 1) {
            throw new \InvalidArgumentException('Currency must be a three-letter code.');
        }
        if ($charge === '') {
            throw new \InvalidArgumentException('Charge code cannot be empty.');
        }
        $this->configurator = $c;
        $this->currencyCode = strtoupper($currency);
        $this->chargeCode = $charge;
        $this->priceType = $type;
        $this->percentageBase = $type === PriceType::PERCENT ? PercentageBase::SUBTOTAL : null;
        if ($amount < 0) {
            throw new \InvalidArgumentException('Price amount cannot be negative.');
        }
        $this->amount = $amount;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfigurator(): Configurator
    {
        return $this->configurator;
    }

    public function getValue(): ?ConfiguratorValue
    {
        return $this->value;
    }

    public function setValue(?ConfiguratorValue $v): void
    {
        if ($v !== null && $v->getField()->getSection()->getConfigurator() !== $this->configurator) {
            throw new \DomainException('Price rule value belongs to another configurator.');
        }
        if ($v !== null && $this->leadTime !== null) {
            throw new \DomainException('A price rule cannot target both a value and a lead time.');
        }
        $this->value = $v;
        $this->touch();
    }

    public function getLeadTime(): ?ConfiguratorLeadTime
    {
        return $this->leadTime;
    }

    public function setLeadTime(?ConfiguratorLeadTime $leadTime): void
    {
        if ($leadTime !== null && $leadTime->getConfigurator() !== $this->configurator) {
            throw new \DomainException('Price rule lead time belongs to another configurator.');
        }
        if ($leadTime !== null && $this->value !== null) {
            throw new \DomainException('A price rule cannot target both a value and a lead time.');
        }
        $this->leadTime = $leadTime;
        $this->touch();
    }

    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    public function setChannel(?Channel $v): void
    {
        $this->channel = $v;
        $this->touch();
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getChargeCode(): string
    {
        return $this->chargeCode;
    }

    public function setCurrencyCode(string $value): void
    {
        if (preg_match('/^[A-Za-z]{3}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Currency must be a three-letter code.');
        } $this->currencyCode = strtoupper($value);
        $this->touch();
    }

    public function setChargeCode(string $value): void
    {
        $this->chargeCode = $value;
        $this->touch();
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $v): void
    {
        $this->label = $v;
        $this->touch();
    }

    public function getMinimumQuantity(): int
    {
        return $this->minimumQuantity;
    }

    public function setQuantityRange(int $min, ?int $max): void
    {
        if ($min < 1 || ($max !== null && $max < $min)) {
            throw new \InvalidArgumentException('Invalid quantity range.');
        }
        $this->minimumQuantity = $min;
        $this->maximumQuantity = $max;
        $this->touch();
    }

    public function getMaximumQuantity(): ?int
    {
        return $this->maximumQuantity;
    }

    public function getPriceType(): PriceType
    {
        return $this->priceType;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $value): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Price amount cannot be negative.');
        } $this->amount = $value;
        $this->touch();
    }

    public function getMultiplierType(): MultiplierType
    {
        return $this->multiplierType;
    }

    public function setMultiplier(MultiplierType $type, ?ConfiguratorField $field = null): void
    {
        if ($type === MultiplierType::QUANTITY && $this->priceType !== PriceType::FIXED) {
            throw new \DomainException(sprintf('%s price rules cannot use a QUANTITY multiplier.', $this->priceType->value));
        }
        if ($type === MultiplierType::FIELD_VALUE && $field === null) {
            throw new \InvalidArgumentException('FIELD_VALUE requires a multiplier field.');
        }
        // @phpstan-ignore nullsafe.neverNull
        if ($type === MultiplierType::FIELD_VALUE && !in_array($field?->getType(), [FieldType::INTEGER, FieldType::QUANTITY], true)) {
            throw new \DomainException('FIELD_VALUE requires an INTEGER or QUANTITY multiplier field.');
        }
        if ($field !== null && $field->getSection()->getConfigurator() !== $this->configurator) {
            throw new \DomainException('Price rule multiplier field belongs to another configurator.');
        }
        $this->multiplierType = $type;
        $this->multiplierField = $type === MultiplierType::FIELD_VALUE ? $field : null;
        $this->touch();
    }

    public function getMultiplierField(): ?ConfiguratorField
    {
        return $this->multiplierField;
    }

    public function getPercentageBase(): ?PercentageBase
    {
        return $this->percentageBase;
    }

    public function setPercentageBase(?PercentageBase $v): void
    {
        if ($this->priceType !== PriceType::PERCENT && $v !== null) {
            throw new \DomainException('Percentage base is only valid for PERCENT rules.');
        }
        if ($this->priceType === PriceType::PERCENT && $v === null) {
            throw new \DomainException('PERCENT rules require a percentage base.');
        }
        $this->percentageBase = $v;
        $this->touch();
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $v): void
    {
        $this->priority = $v;
        $this->touch();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $v): void
    {
        $this->enabled = $v;
        $this->touch();
    }

    public function appliesTo(int $q): bool
    {
        return $this->enabled && $this->minimumQuantity <= $q && ($this->maximumQuantity === null || $q <= $this->maximumQuantity);
    }

    public function dimensionKey(): string
    {
        $source = 'base';
        if ($this->value !== null) {
            $field = $this->value->getField();
            $source = implode('/', [$field->getSection()->getCode(), $field->getCode(), $this->value->getCode()]);
        }
        if ($this->leadTime !== null) {
            $source = 'lead_time/' . $this->leadTime->getCode();
        }
        $multiplier = $this->multiplierField === null ? '-' : implode('/', [$this->multiplierField->getSection()->getCode(), $this->multiplierField->getCode()]);

        // @phpstan-ignore nullsafe.neverNull
        $percentageBase = $this->priceType === PriceType::PERCENT ? $this->percentageBase?->value ?? PercentageBase::SUBTOTAL->value : '-';

        return implode('|', [$this->configurator->getCode(), $source, $this->chargeCode, $this->priceType->value, $this->multiplierType->value, $multiplier, $percentageBase]);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $now = new \DateTimeImmutable();
        $this->updatedAt = $now > $this->updatedAt ? $now : $this->updatedAt->modify('+1 microsecond');
    }
}
