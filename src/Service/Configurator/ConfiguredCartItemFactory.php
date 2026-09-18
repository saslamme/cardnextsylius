<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\Dto\Configurator\ConfiguratorConfiguration;
use App\Entity\Channel\Channel;
use App\Entity\Order\ConfiguredOrderItem;
use App\Exception\Configurator\InvalidConfigurationException;
use App\Repository\Configurator\ConfiguratorRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final readonly class ConfiguredCartItemFactory
{
    public function __construct(
        private ConfiguratorRepository $configurators,
        private ConfiguratorPriceCalculator $calculator,
        private ConfiguredOrderItemSnapshotFactory $snapshots,
        private ChannelContextInterface $channelContext,
        private CurrencyContextInterface $currencyContext,
        private LocaleContextInterface $localeContext,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function create(string $code, array $payload): ConfiguredOrderItem
    {
        $configurator = $this->configurators->findEnabledByCode($code);
        $channel = $this->channelContext->getChannel();
        $quantity = $payload['quantity'] ?? null;
        $selections = $payload['selections'] ?? null;
        $leadTime = $payload['leadTimeCode'] ?? null;
        $channelCode = $channel->getCode();

        if ($configurator === null || !$channel instanceof Channel || !$configurator->hasChannel($channel) || !is_int($quantity) || $quantity < 1 || !is_array($selections) || $channelCode === null || ($leadTime !== null && !is_string($leadTime))) {
            throw new InvalidConfigurationException('Invalid configured cart item payload.');
        }
        $validatedSelections = [];
        foreach ($selections as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidConfigurationException('Configuration selection keys must be strings.');
            }
            $validatedSelections[$key] = $value;
        }

        // Currency and channel intentionally come from the current context, never the snapshot.
        $configuration = new ConfiguratorConfiguration($code, $quantity, $this->currencyContext->getCurrencyCode(), $channelCode, $validatedSelections, [], $leadTime);
        $price = $this->calculator->calculate($configuration, $channel, $configuration->currencyCode);

        return $this->snapshots->create($configurator, $configuration, $price, $this->localeContext->getLocaleCode());
    }
}
