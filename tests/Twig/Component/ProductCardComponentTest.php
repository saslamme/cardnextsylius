<?php

declare(strict_types=1);

namespace App\Tests\Twig\Component;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Twig\Component\ProductCardComponent;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final class ProductCardComponentTest extends TestCase
{
    private Channel $channel;

    protected function setUp(): void
    {
        $this->channel = new Channel();
        $this->channel->setCode('CARDNEXT_DE');
    }

    public function testItSelectsAnEnabledVariantWithAChannelPrice(): void
    {
        $component = $this->mount($this->product($this->variant('SKU', true, 1299)));

        self::assertSame('SKU', $component->variant?->getCode());
        self::assertSame(1299, $component->channelPricing?->getPrice());
    }

    public function testItKeepsAProductWithoutChannelPricingRenderable(): void
    {
        $component = $this->mount($this->product($this->variant('LEGACY__KJJWG1', true)));

        self::assertSame('LEGACY__KJJWG1', $component->variant?->getCode());
        self::assertNull($component->channelPricing);
    }

    public function testItTreatsANullChannelPriceAsUnpriced(): void
    {
        $component = $this->mount($this->product($this->variant('NULL_PRICE', true, null, true)));

        self::assertSame('NULL_PRICE', $component->variant?->getCode());
        self::assertNull($component->channelPricing);
    }

    public function testItPrefersAPricedEnabledVariantRegardlessOfCollectionOrder(): void
    {
        $component = $this->mount($this->product(
            $this->variant('A_WITHOUT_PRICE', true),
            $this->variant('B_WITH_PRICE', true, 2499),
        ));

        self::assertSame('B_WITH_PRICE', $component->variant?->getCode());
        self::assertSame(2499, $component->channelPricing?->getPrice());
    }

    public function testItUsesAStableEnabledFallbackWhenNoVariantHasAPrice(): void
    {
        $component = $this->mount($this->product(
            $this->variant('Z_LAST', true),
            $this->variant('A_FIRST', true),
        ));

        self::assertSame('A_FIRST', $component->variant?->getCode());
        self::assertNull($component->channelPricing);
    }

    public function testItMarksAnEnabledProductInTheCurrentChannelAsEligible(): void
    {
        $product = $this->product($this->variant('CURRENT', true, 1299));
        $product->setEnabled(true);
        $product->addChannel($this->channel);

        self::assertTrue($this->mount($product)->channelEligible);
    }

    public function testItRejectsAProductAssignedOnlyToAnotherChannelWithoutLeakingItsPrice(): void
    {
        $otherChannel = new Channel();
        $otherChannel->setCode('OTHER_MARKET');
        $variant = new ProductVariant();
        $variant->setCode('OTHER_PRICE');
        $variant->setEnabled(true);
        $pricing = new ChannelPricing();
        $pricing->setChannelCode('OTHER_MARKET');
        $pricing->setPrice(9900);
        $variant->addChannelPricing($pricing);
        $product = $this->product($variant);
        $product->setEnabled(true);
        $product->addChannel($otherChannel);

        $component = $this->mount($product);

        self::assertFalse($component->channelEligible);
        self::assertNull($component->channelPricing);
    }

    public function testItRejectsADisabledProductInTheCurrentChannel(): void
    {
        $product = $this->product($this->variant('DISABLED_PRODUCT', true, 1299));
        $product->setEnabled(false);
        $product->addChannel($this->channel);

        self::assertFalse($this->mount($product)->channelEligible);
    }

    public function testItNeverSelectsADisabledVariantEvenWhenItHasTheOnlyPrice(): void
    {
        $component = $this->mount($this->product(
            $this->variant('A_DISABLED', false, 1299),
            $this->variant('B_ENABLED', true),
        ));

        self::assertSame('B_ENABLED', $component->variant?->getCode());
        self::assertNull($component->channelPricing);
    }

    private function mount(Product $product): ProductCardComponent
    {
        $context = $this->createMock(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($this->channel);
        $component = new ProductCardComponent($context);
        $component->product = $product;
        $component->postMount();

        return $component;
    }

    private function product(ProductVariant ...$variants): Product
    {
        $product = new Product();
        foreach ($variants as $variant) {
            $product->addVariant($variant);
        }

        return $product;
    }

    private function variant(string $code, bool $enabled, ?int $price = null, bool $addPricing = false): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setCode($code);
        $variant->setEnabled($enabled);

        if ($price !== null || $addPricing) {
            $pricing = new ChannelPricing();
            $pricing->setChannelCode((string) $this->channel->getCode());
            $pricing->setPrice($price);
            $variant->addChannelPricing($pricing);
        }

        return $variant;
    }
}
