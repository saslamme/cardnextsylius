<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\B2BPriceResolver;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class B2BPriceExtension extends AbstractExtension
{
    public function __construct(
        private readonly B2BPriceResolver $priceResolver,
        private readonly ChannelContextInterface $channelContext,
        private readonly CustomerContextInterface $customerContext,
        private readonly CurrencyContextInterface $currencyContext,
        private readonly CurrencyConverterInterface $currencyConverter,
        private readonly LocaleContextInterface $localeContext,
        private readonly MoneyFormatterInterface $moneyFormatter,
        private readonly ProductVariantPricesCalculatorInterface $pricesCalculator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cardnext_b2b_tier_groups', [$this, 'getProductTierGroups']),
            new TwigFunction('cardnext_b2b_variant_tiers', [$this, 'getVariantTiers']),
            new TwigFunction('cardnext_b2b_variant_from_price', [$this, 'getVariantFromPrice']),
        ];
    }

    /** @return array{price:int,formatted_price:string,min_quantity:int,is_from_price:bool} */
    public function getVariantFromPrice(ProductVariantInterface $variant): array
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            throw new \LogicException('The storefront channel must be a Sylius core channel.');
        }

        $currentPrice = $this->pricesCalculator->calculate($variant, ['channel' => $channel]);
        $lowestTier = $this->priceResolver->findLowestQuantityPrice(
            $variant,
            $channel,
            $currentPrice,
            $this->customerContext->getCustomer(),
        );

        if ($lowestTier === null) {
            return [
                'price' => $currentPrice,
                'formatted_price' => $this->formatPrice($currentPrice),
                'min_quantity' => 1,
                'is_from_price' => false,
            ];
        }

        return [
            'price' => $lowestTier['price'],
            'formatted_price' => $this->formatPrice($lowestTier['price']),
            'min_quantity' => $lowestTier['min_quantity'],
            'is_from_price' => true,
        ];
    }

    /**
     * @return list<array{
     *   variant:ProductVariantInterface,
     *   variant_label:string,
     *   customer_group_code:string,
     *   tiers:list<array{min_quantity:int, price:int, formatted_price:string, source:string}>
     * }>
     */
    public function getProductTierGroups(ProductInterface $product): array
    {
        $channel = $this->channelContext->getChannel();
        $customer = $this->customerContext->getCustomer();
        $groups = [];

        foreach ($product->getEnabledVariants() as $variant) {
            $tiers = $this->priceResolver->getEffectiveTiers($variant, $channel, $customer); // @phpstan-ignore-line
            if ($tiers === []) {
                continue;
            }

            $formattedTiers = [];
            foreach ($tiers as $tier) {
                $formattedTiers[] = [
                    'min_quantity' => $tier['min_quantity'],
                    'price' => $tier['price'],
                    'formatted_price' => $this->formatPrice($tier['price']),
                    'source' => $tier['source'],
                ];
            }

            $groups[] = [
                'variant' => $variant,
                'variant_label' => $variant->getName() ?: (string) $variant->getCode(),
                'customer_group_code' => trim((string) $customer?->getGroup()?->getCode()),
                'tiers' => $formattedTiers,
            ];
        }

        // @phpstan-ignore return.type
        return $groups;
    }

    /**
     * Compact price ladder for the currently selected variant.
     *
     * Only quantity breaks above 1 are returned because the normal price
     * is already rendered directly above the ladder.
     *
     * @return list<array{
     *   min_quantity:int,
     *   price:int,
     *   formatted_price:string,
     *   saving:int,
     *   formatted_saving:string,
     *   saving_percent:int,
     *   source:string
     * }>
     */
    public function getVariantTiers(ProductVariantInterface $variant): array
    {
        $channel = $this->channelContext->getChannel();
        $customer = $this->customerContext->getCustomer();

        // @phpstan-ignore argument.type
        $tiers = $this->priceResolver->getEffectiveTiers($variant, $channel, $customer);
        if ($tiers === []) {
            return [];
        }

        $referencePrice = $tiers[0]['price'];
        if ($referencePrice <= 0) {
            return [];
        }

        $result = [];

        foreach ($tiers as $tier) {
            if ($tier['min_quantity'] <= 1) {
                continue;
            }

            $saving = max(0, $referencePrice - $tier['price']);
            $savingPercent = $saving > 0
                ? (int) round(($saving / $referencePrice) * 100)
                : 0;

            $result[] = [
                'min_quantity' => $tier['min_quantity'],
                'price' => $tier['price'],
                'formatted_price' => $this->formatPrice($tier['price']),
                'saving' => $saving,
                'formatted_saving' => $this->formatPrice($saving),
                'saving_percent' => $savingPercent,
                'source' => $tier['source'],
            ];
        }

        return $result;
    }

    private function formatPrice(int $price): string
    {
        $channel = $this->channelContext->getChannel();
        $targetCurrency = $this->currencyContext->getCurrencyCode();
        // @phpstan-ignore method.notFound
        $baseCurrency = $channel->getBaseCurrency()?->getCode() ?? $targetCurrency;

        $converted = $this->currencyConverter->convert($price, $baseCurrency, $targetCurrency);

        return $this->moneyFormatter->format(
            $converted,
            $targetCurrency,
            $this->localeContext->getLocaleCode(),
        );
    }
}
