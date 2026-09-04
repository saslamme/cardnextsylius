<?php

declare(strict_types=1);

namespace App\Tests\Bundle;

use App\Bundle\BundleViewResolver;
use App\Entity\Product\ProductBundle;
use App\Entity\Product\ProductBundleChannel;
use App\Entity\Product\ProductBundleItem;
use App\Twig\BundleExtension;
use PHPUnit\Framework\TestCase;

final class BundleArchitectureTest extends TestCase
{
    public function testProcessorRunsAfterB2bAndRebuildsAdjustments(): void
    {
        $b2b = file_get_contents(__DIR__.'/../../src/OrderProcessing/B2BPriceOrderProcessor.php');
        $bundle = file_get_contents(__DIR__.'/../../src/OrderProcessing/BundleDiscountOrderProcessor.php');
        self::assertIsString($b2b);
        self::assertIsString($bundle);
        self::assertStringContainsString("priority' => 49", $b2b);
        self::assertStringContainsString("priority' => 40", $bundle);
        self::assertStringContainsString('removeAdjustments(self::ADJUSTMENT_TYPE)', $bundle);
    }

    public function testBundleCartMetadataIsIndependentFromMaintenanceMetadata(): void
    {
        $source = file_get_contents(__DIR__.'/../../src/Entity/Order/OrderItem.php');
        self::assertIsString($source);
        self::assertStringContainsString('parentItem', $source);
        self::assertStringContainsString('bundleGroupKey', $source);
        self::assertStringContainsString('BUNDLE_ROLE_COMPONENT', $source);
    }

    public function testFrontendNeverPostsAClientDiscount(): void
    {
        $template = file_get_contents(__DIR__.'/../../templates/shop/product/_bundles.html.twig');
        self::assertIsString($template);
        self::assertStringNotContainsString('name="discount"', $template);
        self::assertStringContainsString("csrf_token('bundle_add_'", $template);
    }

    public function testBundleStorefrontUsesTheCardnextPresentationContract(): void
    {
        $template = file_get_contents(__DIR__.'/../../templates/shop/product/_bundles.html.twig');
        self::assertIsString($template);

        self::assertStringContainsString('class="cn-container"', $template);
        self::assertStringContainsString("component('sylius_shop:main_image'", $template);
        self::assertSame(2, substr_count($template, "filter: 'cardnext_product_card'"));
        self::assertGreaterThanOrEqual(2, substr_count($template, 'cardnext_product_url('));
        self::assertStringContainsString('cn-bundle__products', $template);
        self::assertStringContainsString('cn-bundle__plus', $template);
        self::assertStringContainsString('cn-bundle-summary', $template);
        self::assertStringContainsString('cn-bundle-product--main', $template);
        self::assertSame(1, substr_count($template, 'name="components[]"'));
        self::assertStringContainsString("csrf_token('bundle_add_'", $template);
        self::assertStringNotContainsString('style="width:', $template);
        $mainProductStart = strpos($template, 'cn-bundle-product--main');
        self::assertIsInt($mainProductStart);
        $mainProductEnd = strpos($template, '</article>', $mainProductStart);
        self::assertIsInt($mainProductEnd);
        self::assertStringNotContainsString('type="checkbox"', substr($template, $mainProductStart, $mainProductEnd - $mainProductStart));
    }

    public function testBundleEntitiesMapSnakeCaseColumnsCreatedByTheMigration(): void
    {
        self::assertSame('main_product_id', $this->mappingName(ProductBundle::class, 'mainProduct', 'JoinColumn'));
        self::assertSame('created_at', $this->mappingName(ProductBundle::class, 'createdAt', 'Column'));
        self::assertSame('updated_at', $this->mappingName(ProductBundle::class, 'updatedAt', 'Column'));
        self::assertSame('bundle_id', $this->mappingName(ProductBundleItem::class, 'bundle', 'JoinColumn'));
        self::assertSame('variant_id', $this->mappingName(ProductBundleItem::class, 'variant', 'JoinColumn'));
        self::assertSame('bundle_id', $this->mappingName(ProductBundleChannel::class, 'bundle', 'JoinColumn'));
        self::assertSame('channel_id', $this->mappingName(ProductBundleChannel::class, 'channel', 'JoinColumn'));
        self::assertSame('discount_type', $this->mappingName(ProductBundleChannel::class, 'discountType', 'Column'));
        self::assertSame('fixed_discount', $this->mappingName(ProductBundleChannel::class, 'fixedDiscount', 'Column'));
        self::assertSame('percentage_discount', $this->mappingName(ProductBundleChannel::class, 'percentageDiscount', 'Column'));
    }

    public function testBundleTemplateUsesTheProductFromTwigHookContext(): void
    {
        $template = file_get_contents(__DIR__.'/../../templates/shop/product/_bundles.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString(
            '{% set product = hookable_metadata.context.product|default(null) %}',
            $template,
        );
        self::assertStringContainsString('cardnext_product_bundles(product)', $template);
    }

    public function testMissingHookProductReturnsNoBundles(): void
    {
        $resolver = (new \ReflectionClass(BundleViewResolver::class))->newInstanceWithoutConstructor();

        self::assertSame([], (new BundleExtension($resolver))->bundles(null));
    }

    /** @param class-string $class */
    private function mappingName(string $class, string $property, string $attribute): string
    {
        $attributes = (new \ReflectionProperty($class, $property))->getAttributes('Doctrine\\ORM\\Mapping\\'.$attribute);
        self::assertCount(1, $attributes);

        $name = $attributes[0]->getArguments()['name'] ?? null;
        self::assertIsString($name);

        return $name;
    }
}
