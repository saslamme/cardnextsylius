<?php

declare(strict_types=1);

namespace App\Tests\Bundle;

use App\Entity\Channel\Channel;
use App\Entity\Product\Product;
use App\Entity\Product\ProductBundle;
use App\Entity\Product\ProductBundleChannel;
use App\Entity\Product\ProductBundleItem;
use App\Entity\Product\ProductVariant;
use App\Form\DataTransformer\BasisPointsToPercentageTransformer;
use App\Form\DataTransformer\MinorUnitsToMoneyTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Validation;

final class BundleAdminTest extends TestCase
{
    public function testAdminTemplateRendersAllNestedCollectionsAndControls(): void
    {
        $section = (string) file_get_contents(__DIR__.'/../../templates/admin/cardnext/product/section.html.twig');
        $collection = (string) file_get_contents(__DIR__.'/../../templates/admin/cardnext/product/_bundle_collection.html.twig');
        $controller = (string) file_get_contents(__DIR__.'/../../assets/admin/controllers/cardnext_bundle_collection_controller.js');

        self::assertStringContainsString("form': hookable_metadata.context.form", $section);
        self::assertStringContainsString('form.bundles', $collection);
        self::assertStringContainsString('Bundle hinzufügen', $collection);
        self::assertStringContainsString('Bestandteil hinzufügen', $collection);
        self::assertStringContainsString('Channel hinzufügen', $collection);
        self::assertStringContainsString('data-prototype', $collection);
        self::assertStringContainsString("insertAdjacentHTML('beforeend'", $controller);
        self::assertStringContainsString("closest('[data-collection-entry]')?.remove()", $controller);
    }

    public function testAdminDiscountValuesAreConvertedWithoutFloats(): void
    {
        self::assertSame(2500, (new MinorUnitsToMoneyTransformer())->reverseTransform('25,00'));
        self::assertSame('25,00', (new MinorUnitsToMoneyTransformer())->transform(2500));
        self::assertSame(500, (new BasisPointsToPercentageTransformer())->reverseTransform('5'));
        self::assertSame(750, (new BasisPointsToPercentageTransformer())->reverseTransform('7,5'));
    }

    public function testMainProductAndDuplicateChannelAreRejected(): void
    {
        $product = new Product();
        $variant = new ProductVariant();
        $variant->setProduct($product);
        $bundle = new ProductBundle();
        $product->addBundle($bundle);
        $item = new ProductBundleItem();
        $item->setVariant($variant);
        $bundle->addItem($item);

        $channel = new Channel();
        $channel->setCode('CARDNEXT_DE');
        foreach ([1, 2] as $unused) {
            $configuration = new ProductBundleChannel();
            $configuration->setChannel($channel);
            $bundle->addChannelConfiguration($configuration);
        }

        $violations = Validation::createValidator()->validate($bundle, new Callback('validateBundle'));
        $messages = implode(' ', array_map(static fn ($violation): string => sprintf('%s', $violation->getMessage()), iterator_to_array($violations)));
        self::assertStringContainsString('Hauptprodukt', $messages);
        self::assertStringContainsString('bereits konfiguriert', $messages);
    }

    public function testDefaultsAndCollectionRemovalSupportExistingProductsWithoutBundles(): void
    {
        $product = new Product();
        self::assertCount(0, $product->getBundles());

        $bundle = new ProductBundle();
        $item = new ProductBundleItem();
        $channel = new ProductBundleChannel();
        self::assertTrue($bundle->isEnabled());
        self::assertSame(10, $bundle->getPosition());
        self::assertTrue($item->isEnabled());
        self::assertSame(1, $item->getQuantity());
        self::assertSame(10, $item->getPosition());
        self::assertTrue($channel->isEnabled());
        self::assertSame(ProductBundleChannel::DISCOUNT_NONE, $channel->getDiscountType());

        $product->addBundle($bundle);
        $bundle->addItem($item);
        $bundle->addChannelConfiguration($channel);
        $bundle->removeItem($item);
        $bundle->removeChannelConfiguration($channel);
        $product->removeBundle($bundle);
        self::assertCount(0, $product->getBundles());
    }
}
