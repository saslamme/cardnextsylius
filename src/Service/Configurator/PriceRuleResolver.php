<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\Entity\Configurator\ConfiguratorPriceRule;
use App\Exception\Configurator\AmbiguousPriceRuleException;

final class PriceRuleResolver
{
    /** @param iterable<ConfiguratorPriceRule> $rules @return list<ConfiguratorPriceRule> */
    public function resolve(iterable $rules, int $quantity, string $channelCode, string $currencyCode): array
    {
        $dimensions = [];
        foreach ($rules as $r) {
            if (!$r->appliesTo($quantity) || $r->getCurrencyCode() !== strtoupper($currencyCode)) {
                continue;
            }
            $ruleChannel = $r->getChannel()?->getCode();
            if ($ruleChannel !== null && $ruleChannel !== $channelCode) {
                continue;
            }
            $key = $r->dimensionKey();
            $specific = $ruleChannel !== null;
            if (!isset($dimensions[$key]) || ($specific && !$dimensions[$key]['specific'])) {
                $dimensions[$key] = ['specific' => $specific, 'rules' => [$r]];
            } elseif ($specific === $dimensions[$key]['specific']) {
                $dimensions[$key]['rules'][] = $r;
            }
        }
        $resolved = [];
        foreach ($dimensions as $dimension) {
            if (count($dimension['rules']) > 1) {
                throw new AmbiguousPriceRuleException('More than one applicable rule exists for a price dimension.');
            }
            $resolved[] = $dimension['rules'][0];
        }usort($resolved, fn ($a, $b) => [$a->getPriceType()->value, -$a->getPriority(), $a->getChargeCode(), $a->dimensionKey(), $a->getId() ?? \PHP_INT_MAX] <=> [$b->getPriceType()->value, -$b->getPriority(), $b->getChargeCode(), $b->dimensionKey(), $b->getId() ?? \PHP_INT_MAX]);

        return $resolved;
    }
}
