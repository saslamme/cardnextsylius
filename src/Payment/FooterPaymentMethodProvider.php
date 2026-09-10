<?php

declare(strict_types=1);

namespace App\Payment;

use Mollie\Api\Types\PaymentMethod as MolliePaymentMethod;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\MolliePlugin\Entity\GatewayConfigInterface as MollieGatewayInterface;
use Sylius\MolliePlugin\Entity\MollieGatewayConfigInterface;
use Sylius\MolliePlugin\Payum\Factory\MollieGatewayFactory;
use Sylius\MolliePlugin\Repository\MollieGatewayConfigRepositoryInterface;

final class FooterPaymentMethodProvider
{
    /** @var array<string, list<array{code: string, label: string}>> */
    private array $requestCache = [];

    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly MollieGatewayConfigRepositoryInterface $mollieMethodRepository,
    ) {
    }

    /** @return list<array{code: string, label: string}> */
    public function provide(): array
    {
        $channel = $this->channelContext->getChannel();

        return $channel instanceof ChannelInterface ? $this->provideForChannel($channel) : [];
    }

    /** @return list<array{code: string, label: string}> */
    public function provideForChannel(ChannelInterface $channel): array
    {
        $cacheKey = $channel->getCode() ?? (string) spl_object_id($channel);
        if (isset($this->requestCache[$cacheKey])) {
            return $this->requestCache[$cacheKey];
        }

        $items = [];
        foreach ($this->paymentMethodRepository->findEnabledForChannel($channel) as $paymentMethod) {
            $gateway = $paymentMethod->getGatewayConfig();
            if ($gateway?->getFactoryName() === MollieGatewayFactory::FACTORY_NAME) {
                if (!$gateway instanceof MollieGatewayInterface) {
                    continue;
                }

                foreach ($this->enabledMollieMethods($gateway) as $method) {
                    foreach ($this->presentMollieMethod($method) as $item) {
                        $items[$item['code']] ??= $item;
                    }
                }

                continue;
            }

            $code = $this->normaliseCode($paymentMethod->getCode() ?? $paymentMethod->getName() ?? '');
            if ($code === '') {
                continue;
            }

            $items[$code] ??= ['code' => $code, 'label' => $paymentMethod->getName() ?? $paymentMethod->getCode() ?? $code];
        }

        return $this->requestCache[$cacheKey] = array_values($items);
    }

    /** @return list<MollieGatewayConfigInterface> */
    private function enabledMollieMethods(MollieGatewayInterface $gateway): array
    {
        try {
            return array_values($this->mollieMethodRepository->findAllEnabledByGateway($gateway));
        } catch (\Throwable) {
            // A partially configured gateway must not break every storefront page.
            return [];
        }
    }

    /** @return list<array{code: string, label: string}> */
    private function presentMollieMethod(MollieGatewayConfigInterface $method): array
    {
        $methodId = strtolower(trim((string) $method->getMethodId()));
        if ($methodId === '') {
            return [];
        }

        if ($methodId === MolliePaymentMethod::CREDITCARD) {
            return [
                ['code' => 'visa', 'label' => 'Visa'],
                ['code' => 'mastercard', 'label' => 'Mastercard'],
            ];
        }

        $knownLabels = [
            MolliePaymentMethod::PAYPAL => 'PayPal',
            MolliePaymentMethod::APPLEPAY => 'Apple Pay',
            MolliePaymentMethod::IDEAL => 'iDEAL',
            MolliePaymentMethod::BANCONTACT => 'Bancontact',
            MolliePaymentMethod::EPS => 'EPS',
        ];
        if (str_starts_with($methodId, 'klarna')) {
            return [['code' => 'klarna', 'label' => 'Klarna']];
        }

        $code = $this->normaliseCode($methodId);
        $fallbackLabel = trim((string) $method->getName());

        return [[
            'code' => $code,
            'label' => $knownLabels[$methodId] ?? ($fallbackLabel !== '' ? $fallbackLabel : $this->humanise($methodId)),
        ]];
    }

    private function normaliseCode(string $value): string
    {
        $code = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $value));

        return match ($code) {
            'rechnung', 'purchaseonaccount' => 'invoice',
            'vorkasse', 'advancepayment', 'banktransfer' => 'prepayment',
            'applepay' => 'applepay',
            'paypal' => 'paypal',
            default => $code,
        };
    }

    private function humanise(string $methodId): string
    {
        $label = preg_replace('/[_-]+/', ' ', $methodId) ?? $methodId;

        return ucwords($label);
    }
}
