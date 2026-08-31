<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\Dto\Configurator\ConfiguratorConfiguration;
use App\Dto\Configurator\ConfiguratorPriceResult;
use App\Dto\Configurator\PriceBreakdownLine;
use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorPriceRule;
use App\Entity\Configurator\ConfiguratorValue;
use App\Enum\Configurator\MultiplierType;
use App\Enum\Configurator\PercentageBase;
use App\Enum\Configurator\PriceType;
use App\Exception\Configurator\ConfiguratorNotFoundException;
use App\Exception\Configurator\InvalidConfigurationException;
use App\Exception\Configurator\MissingPriceRuleException;
use App\Repository\Configurator\ConfiguratorPriceRuleRepository;
use App\Repository\Configurator\ConfiguratorRepository;
use Sylius\Component\Channel\Model\ChannelInterface;

final readonly class ConfiguratorPriceCalculator
{
    public function __construct(
        private ConfiguratorRepository $configurators,
        private ConfiguratorPriceRuleRepository $rules,
        private ConfiguratorValidator $validator,
        private PriceRuleResolver $resolver,
    ) {
    }

    public function calculate(ConfiguratorConfiguration $configuration, ChannelInterface $channel, string $currencyCode): ConfiguratorPriceResult
    {
        if ($configuration->channelCode !== $channel->getCode()) {
            throw new InvalidConfigurationException('Configuration channel does not match calculation channel.');
        }
        if (strtoupper($configuration->currencyCode) !== strtoupper($currencyCode)) {
            throw new InvalidConfigurationException('Configuration currency does not match calculation currency.');
        }

        $model = $this->configurators->findEnabledByCode($configuration->configuratorCode)
            ?? throw new ConfiguratorNotFoundException($configuration->configuratorCode);
        $validation = $this->validator->validate($configuration, $model, $model->getDependencies());
        if (!$validation->isValid()) {
            throw new InvalidConfigurationException(json_encode($validation, \JSON_THROW_ON_ERROR));
        }

        $values = $this->selectedValues($model, $configuration->selections);
        $leadTime = null;
        foreach ($model->getLeadTimes() as $candidate) {
            if ($candidate->isEnabled() && $candidate->getCode() === $configuration->leadTimeCode) {
                $leadTime = $candidate;
            }
        }
        $ids = array_values(array_filter(array_map(static fn (ConfiguratorValue $value): ?int => $value->getId(), $values)));
        $candidates = $this->rules->findApplicable($model, $ids, $channel, $currencyCode, $configuration->quantity, $leadTime?->getId());

        $result = $this->calculateRules($configuration, $candidates, $channel->getCode(), $currencyCode);

        return new ConfiguratorPriceResult($result->quantity, $result->currencyCode, $result->baseUnitAmount, $result->optionsUnitAmount, $result->unitAmount, $result->unitTotal, $result->fixedTotal, $result->percentageTotal, $result->total, $result->breakdown, $leadTime?->getCode(), $leadTime?->getName(), $leadTime?->getWorkingDays());
    }

    /** @param iterable<ConfiguratorPriceRule> $rules */
    private function calculateRules(ConfiguratorConfiguration $cfg, iterable $rules, string $channelCode, string $currency): ConfiguratorPriceResult
    {
        $resolved = $this->resolver->resolve($rules, $cfg->quantity, $channelCode, $currency);
        $hasBase = false;
        $baseUnit = $optionsUnit = $fixed = $percent = 0;
        $lines = [];
        $deferred = [];

        foreach ($resolved as $rule) {
            if ($rule->getPriceType() === PriceType::PERCENT) {
                $deferred[] = $rule;

                continue;
            }

            $isBase = $rule->getValue() === null && $rule->getLeadTime() === null;
            if ($isBase && $rule->getPriceType() === PriceType::UNIT) {
                $hasBase = true;
            }

            $factor = $this->factor($rule, $cfg);
            if ($rule->getPriceType() === PriceType::UNIT) {
                // Quantity is applied exactly once by unitTotal. UNIT rules only
                // permit NONE or FIELD_VALUE multipliers.
                $effectiveUnit = $rule->getAmount() * ($rule->getMultiplierType() === MultiplierType::FIELD_VALUE ? $factor : 1);
                $isBase ? $baseUnit += $effectiveUnit : $optionsUnit += $effectiveUnit;
                $lines[] = $this->line($rule, ($rule->getMultiplierType() === MultiplierType::FIELD_VALUE ? $factor : 1) * $cfg->quantity, $effectiveUnit * $cfg->quantity);
            } else {
                $amount = $rule->getAmount() * $factor;
                $fixed += $amount;
                $lines[] = $this->line($rule, $factor, $amount);
            }
        }

        if (!$hasBase) {
            throw new MissingPriceRuleException('No applicable base UNIT price rule was found.');
        }

        $unitTotal = ($baseUnit + $optionsUnit) * $cfg->quantity;
        foreach ($deferred as $rule) {
            // @phpstan-ignore match.unhandled
            $base = match ($rule->getPercentageBase() ?? PercentageBase::SUBTOTAL) {
                PercentageBase::BASE => $baseUnit * $cfg->quantity,
                PercentageBase::OPTIONS => $optionsUnit * $cfg->quantity,
                PercentageBase::SUBTOTAL => $unitTotal + $fixed + $percent,
            };
            // Percentage bases already represent the complete order. QUANTITY is
            // rejected by the rule invariant; FIELD_VALUE remains explicit.
            $factor = $this->factor($rule, $cfg);
            $amount = self::basisPoints($base, $rule->getAmount()) * $factor;
            $percent += $amount;
            $lines[] = $this->line($rule, $factor, $amount, $base);
        }

        return new ConfiguratorPriceResult($cfg->quantity, strtoupper($currency), $baseUnit, $optionsUnit, $baseUnit + $optionsUnit, $unitTotal, $fixed, $percent, $unitTotal + $fixed + $percent, $lines);
    }

    public static function basisPoints(int $amount, int $basisPoints): int
    {
        $product = $amount * $basisPoints;

        return $product >= 0 ? intdiv($product + 5000, 10000) : -intdiv(-$product + 5000, 10000);
    }

    private function factor(ConfiguratorPriceRule $rule, ConfiguratorConfiguration $cfg): int
    {
        return match ($rule->getMultiplierType()) {
            MultiplierType::NONE => 1,
            MultiplierType::QUANTITY => $cfg->quantity,
            MultiplierType::FIELD_VALUE => $this->fieldMultiplier($rule, $cfg),
        };
    }

    private function fieldMultiplier(ConfiguratorPriceRule $rule, ConfiguratorConfiguration $cfg): int
    {
        $code = $rule->getMultiplierField()?->getCode() ?? '';
        $value = $cfg->selections[$code] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new InvalidConfigurationException("Multiplier field $code must be a non-negative integer.");
        }

        return (int) $value;
    }

    private function line(ConfiguratorPriceRule $rule, int $multiplier, int $amount, ?int $base = null): PriceBreakdownLine
    {
        $value = $rule->getValue();
        $leadTime = $rule->getLeadTime();

        return new PriceBreakdownLine($leadTime ? 'lead_time' : ($value ? 'value' : 'configurator'), $leadTime?->getCode() ?? $value?->getCode() ?? $rule->getConfigurator()->getCode(), $value?->getField()->getCode(), $value?->getCode(), $rule->getChargeCode(), $rule->getPriceType()->value, $rule->getLabel() ?? $leadTime?->getName(), $rule->getPriceType() === PriceType::UNIT ? $rule->getAmount() : null, $base, $multiplier, $amount);
    }

    /** @param array<string, mixed> $selections @return list<ConfiguratorValue> */
    // @phpstan-ignore missingType.iterableValue
    private function selectedValues(Configurator $configurator, array $selections): array
    {
        $result = [];
        foreach ($configurator->getSections() as $section) {
            foreach ($section->getFields() as $field) {
                foreach ((array) ($selections[$field->getCode()] ?? []) as $code) {
                    foreach ($field->getValues() as $value) {
                        if ($value->getCode() === $code) {
                            $result[] = $value;
                        }
                    }
                }
            }
        }

        return $result;
    }
}
