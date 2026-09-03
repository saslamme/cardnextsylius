<?php

declare(strict_types=1);

namespace App\Tests\Maintenance;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Currency\Currency;
use App\Entity\Order\OrderItem;
use App\Entity\Product\Product;
use App\Entity\Product\ProductAssociation;
use App\Entity\Product\ProductAssociationType;
use App\Entity\Product\ProductVariant;
use App\Maintenance\ProductMaintenanceOfferResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final class MaintenanceFeatureTest extends TestCase
{
    public function testProductCanOnlyBeSoldAsAddonAndIsNeverFeatured(): void
    {
        $product = new Product();
        $product->setHomepageFeatured(true);
        $product->setAddonOnly(true);
        self::assertTrue($product->isAddonOnly());
        self::assertFalse($product->isHomepageFeatured());
    }

    public function testResolverOnlyReturnsEnabledAssociatedPricedProductsForChannel(): void
    {
        $channel = new Channel();
        $channel->setCode('DE');
        $currency = new Currency();
        $currency->setCode('EUR');
        $channel->setBaseCurrency($currency);
        $main = new Product();
        $valid = $this->addon($channel, 18900);
        $unpriced = $this->addon($channel, null);
        $type = new ProductAssociationType();
        $type->setCode(ProductMaintenanceOfferResolver::ASSOCIATION_TYPE);
        $association = new ProductAssociation();
        $association->setType($type);
        $association->addAssociatedProduct($valid);
        $association->addAssociatedProduct($unpriced);
        $main->addAssociation($association);
        $context = $this->createMock(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);
        $offers = (new ProductMaintenanceOfferResolver($context))->resolve($main);
        self::assertCount(1, $offers);
        self::assertSame(18900, $offers[0]->price);
        self::assertSame('EUR', $offers[0]->currencyCode);
    }

    public function testMaintenanceOrderItemReferencesParentWithoutChangingParent(): void
    {
        $parent = new OrderItem();
        $addon = new OrderItem();
        $addon->setParentItem($parent);
        $addon->setAddonType(OrderItem::ADDON_TYPE_MAINTENANCE);
        self::assertSame($parent, $addon->getParentItem());
        self::assertTrue($addon->isMaintenanceAddon());
        self::assertNull($parent->getParentItem());
    }

    private function addon(Channel $channel, ?int $price): Product
    {
        $product = new Product();
        $product->setEnabled(true);
        $product->setAddonOnly(true);
        $product->addChannel($channel);
        $variant = new ProductVariant();
        $variant->setCode(uniqid('plan-', true));
        $variant->setEnabled(true);
        $product->addVariant($variant);
        if ($price !== null) {
            $pricing = new ChannelPricing();
            $pricing->setChannelCode('DE');
            $pricing->setPrice($price);
            $variant->addChannelPricing($pricing);
        }

        return $product;
    }
}
