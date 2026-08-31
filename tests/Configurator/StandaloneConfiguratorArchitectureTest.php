<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorTranslation;
use App\Entity\Product\Product;
use App\Entity\Product\ProductTranslation;
use PHPUnit\Framework\TestCase;

final class StandaloneConfiguratorArchitectureTest extends TestCase
{
    public function testConfiguratorOwnsNormalizedLocalizedContentWithoutProduct(): void
    {
        $configurator = new Configurator('cards', 'Cards administration');
        $translation = new ConfiguratorTranslation('de_DE', 'Bedruckte Plastikkarten', '/plastikkarten/plastikkarten-bedrucken/');
        $configurator->addTranslation($translation);

        self::assertSame('plastikkarten/plastikkarten-bedrucken', $translation->getPath());
        self::assertSame($translation, $configurator->getTranslation('de_DE'));
        self::assertFalse(method_exists(Configurator::class, 'getProduct'));
    }

    public function testProductDomainHasNoConfiguratorOrProductKindApi(): void
    {
        self::assertFalse(method_exists(Product::class, 'getConfigurator'));
        self::assertNotSame(Product::class, (new \ReflectionMethod(Product::class, 'isConfigurable'))->getDeclaringClass()->getName());
        self::assertFalse(method_exists(Product::class, 'getProductKind'));
        self::assertFalse(method_exists(ProductTranslation::class, 'getConfiguratorPath'));
    }

    public function testUnsafePublicPathsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ConfiguratorTranslation('de_DE', 'Cards', 'https://example.test/cards?draft=1');
    }

    public function testCheckoutAdminRemainsPartOfStandaloneConfiguratorManagement(): void
    {
        $controller = file_get_contents(\dirname(__DIR__, 2) . '/src/Controller/Admin/ConfiguratorAdminController.php');
        $template = file_get_contents(\dirname(__DIR__, 2) . '/templates/admin/cardnext/configurator/checkout.html.twig');
        $header = file_get_contents(\dirname(__DIR__, 2) . '/templates/admin/cardnext/configurator/_header.html.twig');

        self::assertIsString($controller);
        self::assertIsString($template);
        self::assertIsString($header);
        self::assertStringContainsString("#[Route('/{id}/checkout'", $controller);
        self::assertStringContainsString('find(TaxCategory::class', $controller);
        self::assertStringContainsString('setTaxCategory($taxCategory)', $controller);
        self::assertStringContainsString("getBoolean('shipping_required')", $controller);
        self::assertStringContainsString('name="tax_category_id"', $template);
        self::assertStringContainsString('name="shipping_required"', $template);
        self::assertStringContainsString('Verkauf &amp; Versand', $header);
    }
}
