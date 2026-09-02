<?php

declare(strict_types=1);

namespace App\Payment;

use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;

final class AdyenExpressCheckoutAvailability implements AdyenExpressCheckoutAvailabilityInterface
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function isAvailable(): bool
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            return false;
        }

        foreach ($this->paymentMethodRepository->findEnabledForChannel($channel) as $paymentMethod) {
            if ($paymentMethod->getGatewayConfig()?->getFactoryName() === AdyenClientProviderInterface::FACTORY_NAME) {
                return true;
            }
        }

        return false;
    }
}
