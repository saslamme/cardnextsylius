<?php

declare(strict_types=1);

namespace App\Entity\Configurator;

use App\Enum\Configurator\DependencyEffect;
use App\Enum\Configurator\DependencyOperator;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity] #[ORM\Table(name:'cardnext_configurator_dependency')] #[ORM\Index(name:'IDX_CN_CFG_DEP_LOOKUP', columns:['configurator_id', 'enabled', 'priority'])]
class ConfiguratorDependency
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column(name:'id')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity:Configurator::class, inversedBy: 'dependencies'),ORM\JoinColumn(name:'configurator_id', nullable:false, onDelete:'CASCADE')]
    private Configurator $configurator;

    #[ORM\ManyToOne(targetEntity:ConfiguratorField::class),ORM\JoinColumn(name:'source_field_id', nullable:false, onDelete:'CASCADE')]
    private ConfiguratorField $sourceField;

    #[ORM\Column(name:'operator', enumType:DependencyOperator::class, length:40)]
    private DependencyOperator $operator;

    #[ORM\Column(name:'expected_values', type:'json')]
    // @phpstan-ignore missingType.iterableValue
    private array $expectedValues = [];

    #[ORM\ManyToOne(targetEntity:ConfiguratorField::class),ORM\JoinColumn(name:'target_field_id', nullable:true, onDelete:'CASCADE')]
    private ?ConfiguratorField $targetField = null;

    #[ORM\ManyToOne(targetEntity:ConfiguratorValue::class),ORM\JoinColumn(name:'target_value_id', nullable:true, onDelete:'CASCADE')]
    private ?ConfiguratorValue $targetValue = null;

    #[ORM\Column(name:'effect', enumType:DependencyEffect::class, length:20)]
    private DependencyEffect $effect;

    #[ORM\Column(name:'priority')]
    private int $priority = 0;

    #[ORM\Column(name:'enabled', options:['default' => true])]
    private bool $enabled = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @param list<string|int|bool> $expected */
    public function __construct(Configurator $c, ConfiguratorField $source, DependencyOperator $op, array $expected, DependencyEffect $effect)
    {
        $this->assertFieldBelongsTo($c, $source);
        self::assertExpectedValues($op, $expected);
        $this->configurator = $c;
        $this->sourceField = $source;
        $this->operator = $op;
        $this->expectedValues = $expected;
        $this->effect = $effect;
        $c->addDependency($this);
    }

    public function getConfigurator(): Configurator
    {
        return $this->configurator;
    }

    public function getSourceField(): ConfiguratorField
    {
        return $this->sourceField;
    }

    public function getOperator(): DependencyOperator
    {
        return $this->operator;
    }

    // @phpstan-ignore missingType.iterableValue
    public function getExpectedValues(): array
    {
        return $this->expectedValues;
    }

    public function getTargetField(): ?ConfiguratorField
    {
        return $this->targetField;
    }

    public function setTargetField(?ConfiguratorField $v): void
    {
        if ($v !== null) {
            $this->assertFieldBelongsTo($this->configurator, $v);
        }
        if ($this->targetValue !== null && $this->targetValue->getField() !== $v) {
            throw new \DomainException('Dependency target value does not belong to its target field.');
        }
        $this->targetField = $v;
    }

    public function getTargetValue(): ?ConfiguratorValue
    {
        return $this->targetValue;
    }

    public function setTargetValue(?ConfiguratorValue $v): void
    {
        if ($v !== null) {
            $this->assertFieldBelongsTo($this->configurator, $v->getField());
            if ($this->targetField !== null && $v->getField() !== $this->targetField) {
                throw new \DomainException('Dependency target value does not belong to its target field.');
            }
            $this->targetField ??= $v->getField();
        }
        $this->targetValue = $v;
    }

    public function getEffect(): DependencyEffect
    {
        return $this->effect;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $v): void
    {
        $this->enabled = $v;
    }

    // @phpstan-ignore missingType.iterableValue
    public function setOperator(DependencyOperator $operator, array $expectedValues): void
    {
        self::assertExpectedValues($operator, $expectedValues);
        $this->operator = $operator;
        $this->expectedValues = $expectedValues;
    }

    public function setEffect(DependencyEffect $effect): void
    {
        $this->effect = $effect;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    private function assertFieldBelongsTo(Configurator $configurator, ConfiguratorField $field): void
    {
        if ($field->getSection()->getConfigurator() !== $configurator) {
            throw new \DomainException('Dependency relation crosses configurators.');
        }
    }

    // @phpstan-ignore missingType.iterableValue
    private static function assertExpectedValues(DependencyOperator $operator, array $expected): void
    {
        if ($operator === DependencyOperator::IS_SELECTED) {
            if ($expected !== []) {
                throw new \DomainException('IS_SELECTED does not accept expected values.');
            }

            return;
        }
        if ($expected === [] || !array_is_list($expected)) {
            throw new \DomainException('Dependency operator requires a non-empty expected-value list.');
        }
        if (in_array($operator, [DependencyOperator::GREATER_THAN, DependencyOperator::GREATER_THAN_OR_EQUAL, DependencyOperator::LESS_THAN, DependencyOperator::LESS_THAN_OR_EQUAL], true) &&
            (count($expected) !== 1 || !is_numeric($expected[0]))) {
            throw new \DomainException('Comparison operators require exactly one numeric expected value.');
        }
    }
}
