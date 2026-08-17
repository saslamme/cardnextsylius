<?php

declare(strict_types=1);

namespace Tests\Form;

use App\Entity\Product\Product;
use App\Enum\Product\ProductKind;
use App\Factory\CardnextProductFactory;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Symfony\Component\Yaml\Yaml;

final class CardnextProductCreateFlowTest extends TestCase
{
    public function testProductCreateDropdownSeparatesAllThreeProductConcepts(): void
    {
        $grid = Yaml::parseFile(__DIR__ . '/../../config/packages/cardnext_admin_product_grid.yaml');
        $links = $grid['sylius_grid']['grids']['sylius_admin_product']['actions']['main']['create']['options']['links'];

        self::assertSame('cardnext.admin.product_create.standard', $links['simple']['label']);
        self::assertSame('cardnext.admin.product_create.variant', $links['configurable']['label']);
        self::assertSame('cardnext.admin.product_create.configuration', $links['cardnext_configurable']['label']);
        self::assertSame('cardnext_admin_product_create_configurable', $links['cardnext_configurable']['route']);

        $german = Yaml::parseFile(__DIR__ . '/../../translations/messages.de.yaml');
        self::assertSame('Standardprodukt', $german['cardnext']['admin']['product_create']['standard']);
        self::assertSame('Variantenprodukt', $german['cardnext']['admin']['product_create']['variant']);
        self::assertSame('Konfigurationsprodukt', $german['cardnext']['admin']['product_create']['configuration']);
    }

    public function testDedicatedRouteUsesServerSideConfigurableFactoryBeforeFormCreation(): void
    {
        $route = Yaml::parseFile(__DIR__ . '/../../config/routes/cardnext_admin_product.yaml')['cardnext_admin_product_create_configurable'];

        self::assertSame('/%sylius_admin.path_name%/products/new/configurator', $route['path']);
        self::assertSame(['GET', 'POST'], $route['methods']);
        self::assertSame('createConfigurableWithVariant', $route['defaults']['_sylius']['factory']['method']);
        self::assertSame('Sylius\\Bundle\\AdminBundle\\Form\\Type\\ProductType', $route['defaults']['_sylius']['form']['type']);
    }

    public function testNativeProductFactoriesRemainStandardAndCardnextFactoryIsConfigurable(): void
    {
        $native = $this->createMock(ProductFactoryInterface::class);
        $native->method('createNew')->willReturnCallback(static fn (): Product => new Product());
        $native->method('createWithVariant')->willReturnCallback(static function (): Product {
            $product = new Product();
            $product->addVariant(new \App\Entity\Product\ProductVariant());

            return $product;
        });
        $factory = new CardnextProductFactory($native);

        self::assertSame(ProductKind::STANDARD, $factory->createNew()->getProductKind());
        self::assertSame(ProductKind::STANDARD, $factory->createWithVariant()->getProductKind());

        $configurable = $factory->createConfigurableWithVariant();
        self::assertSame(ProductKind::CONFIGURABLE, $configurable->getProductKind());
        self::assertCount(1, $configurable->getVariants());
    }

    public function testManipulatedPostCannotRegressConfigurationProductToStandard(): void
    {
        $native = $this->createMock(ProductFactoryInterface::class);
        $native->method('createWithVariant')->willReturn(new Product());
        $product = (new CardnextProductFactory($native))->createConfigurableWithVariant();

        self::assertSame(ProductKind::CONFIGURABLE, $product->getProductKind());

        $source = file_get_contents(__DIR__ . '/../../src/Form/Extension/ProductTypeExtension.php');
        self::assertStringContainsString("'disabled' => true", (string) $source);
    }
}
