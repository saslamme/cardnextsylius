<?php

declare(strict_types=1);

namespace App\Tests\Bundle;

use App\Entity\Product\Product;
use App\Entity\Product\ProductBundle;
use App\Entity\Product\ProductBundleChannel;
use App\Entity\Product\ProductBundleItem;
use App\Entity\Product\ProductVariant;
use PHPUnit\Framework\TestCase;

final class ProductBundleTest extends TestCase
{
    public function testItOwnsVariantComponentsAndChannels(): void
    {
        $product = new Product();
        $bundle = new ProductBundle();
        $item = new ProductBundleItem();
        $item->setVariant(new ProductVariant());
        $item->setQuantity(5);
        $channel = new ProductBundleChannel();

        $product->addBundle($bundle);
        $bundle->addItem($item);
        $bundle->addChannelConfiguration($channel);

        self::assertSame($product, $bundle->getMainProduct());
        self::assertSame($bundle, $item->getBundle());
        self::assertSame(5, $bundle->getItems()->first()->getQuantity());
        self::assertSame($bundle, $channel->getBundle());
    }

    public function testFixedDiscountUsesMinorUnitsAndCannotExceedSubtotal(): void
    {
        $configuration = new ProductBundleChannel();
        $configuration->setDiscountType(ProductBundleChannel::DISCOUNT_FIXED);
        $configuration->setFixedDiscount(8400);

        self::assertSame(16800, $configuration->calculateDiscount(200000, 2));
        self::assertSame(1000, $configuration->calculateDiscount(1000));
    }

    public function testPercentageDiscountUsesIntegerBasisPointsAndRounding(): void
    {
        $configuration = new ProductBundleChannel();
        $configuration->setDiscountType(ProductBundleChannel::DISCOUNT_PERCENT);
        $configuration->setPercentageDiscount(500);

        self::assertSame(5665, $configuration->calculateDiscount(113300));
        self::assertSame(1, $configuration->calculateDiscount(10));
    }

    public function testInvalidDiscountTypeAndBundleRoleAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ProductBundleChannel())->setDiscountType('FLOAT');
    }
}
