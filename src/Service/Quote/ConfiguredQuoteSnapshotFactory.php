<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Order\ConfiguredOrderItem;

final class ConfiguredQuoteSnapshotFactory
{
    /** @return array<string, mixed> */
    public function createSnapshot(ConfiguredOrderItem $item): array
    {
        return [
            'configuratorCode' => $item->getConfiguratorCode(), 'configuratorName' => $item->getConfiguratorName(),
            'localeCode' => $item->getLocaleCode(), 'channelCode' => $item->getChannelCode(), 'currencyCode' => $item->getCurrencyCode(),
            'quantity' => $item->getQuantity(), 'configurationHash' => $item->getConfigurationHash(),
            'canonicalConfiguration' => $item->getCanonicalConfiguration(), 'selectionsSnapshot' => $item->getSelectionsSnapshot(),
            'priceBreakdownSnapshot' => $item->getPriceBreakdownSnapshot(), 'baseUnitAmount' => $item->getBaseUnitAmount(),
            'optionsUnitAmount' => $item->getOptionsUnitAmount(), 'unitAmount' => $item->getUnitAmount(), 'unitTotal' => $item->getUnitTotal(),
            'fixedTotal' => $item->getFixedTotal(), 'percentageTotal' => $item->getPercentageTotal(), 'total' => $item->getTotal(),
            'leadTimeCode' => $item->getLeadTimeCode(), 'leadTimeName' => $item->getLeadTimeName(), 'workingDays' => $item->getWorkingDays(),
            'taxCategoryCode' => $item->getTaxCategoryCode(), 'shippingRequired' => $item->isShippingRequired(),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function restore(array $snapshot, int $finalLineTotal): ConfiguredOrderItem
    {
        $required = ['configuratorCode', 'configuratorName', 'localeCode', 'channelCode', 'currencyCode', 'quantity', 'configurationHash', 'canonicalConfiguration', 'selectionsSnapshot', 'priceBreakdownSnapshot', 'baseUnitAmount', 'optionsUnitAmount', 'unitAmount', 'unitTotal', 'fixedTotal', 'percentageTotal'];
        foreach ($required as $key) { if (!array_key_exists($key, $snapshot)) { throw new \DomainException(sprintf('Configured quote snapshot is missing "%s".', $key)); } }

        return new ConfiguredOrderItem(
            (string) $snapshot['configuratorCode'], (string) $snapshot['configuratorName'], (string) $snapshot['localeCode'],
            (string) $snapshot['channelCode'], (string) $snapshot['currencyCode'], (int) $snapshot['quantity'], (string) $snapshot['configurationHash'],
            (array) $snapshot['selectionsSnapshot'], (array) $snapshot['priceBreakdownSnapshot'], (array) $snapshot['canonicalConfiguration'],
            (int) $snapshot['baseUnitAmount'], (int) $snapshot['optionsUnitAmount'], (int) $snapshot['unitAmount'], (int) $snapshot['unitTotal'],
            (int) $snapshot['fixedTotal'], (int) $snapshot['percentageTotal'], $finalLineTotal,
            isset($snapshot['leadTimeCode']) ? (string) $snapshot['leadTimeCode'] : null,
            isset($snapshot['leadTimeName']) ? (string) $snapshot['leadTimeName'] : null,
            isset($snapshot['workingDays']) ? (int) $snapshot['workingDays'] : null,
            isset($snapshot['taxCategoryCode']) ? (string) $snapshot['taxCategoryCode'] : null,
            (bool) ($snapshot['shippingRequired'] ?? true),
        );
    }
}
