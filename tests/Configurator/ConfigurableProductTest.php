<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Configurator\Configurator;
use App\Entity\Product\Product;
use App\Enum\Product\ProductKind;
use App\Service\Configurator\ConfiguratorProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ConfigurableProductTest extends TestCase
{
    public function testNewProductIsStandardByDefault(): void
    {
        $product = new Product();

        self::assertSame(ProductKind::STANDARD, $product->getProductKind());
        self::assertTrue($product->isStandard());
        self::assertFalse($product->isConfigurable());
    }

    public function testConfigurableProductGetsExactlyOneConfigurator(): void
    {
        $product = new Product();
        $product->setCode('PRINTED_CARDS');
        $product->setProductKind(ProductKind::CONFIGURABLE);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Configurator::class));
        $provisioner = new ConfiguratorProvisioner($entityManager);

        $first = $provisioner->ensureConfigurator($product);
        $second = $provisioner->ensureConfigurator($product);

        self::assertInstanceOf(Configurator::class, $first);
        self::assertSame($first, $second);
        self::assertSame($product, $first->getProduct());
        self::assertTrue($first->isEnabled());
        self::assertStringStartsWith('printed_cards_', $first->getCode());
    }

    public function testStandardProductDoesNotGetConfigurator(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        self::assertNull((new ConfiguratorProvisioner($entityManager))->ensureConfigurator(new Product()));
    }

    public function testProductRejectsSecondConfiguratorAndConfiguratorReassignment(): void
    {
        $firstProduct = $this->configurableProduct('FIRST');
        $secondProduct = $this->configurableProduct('SECOND');
        $configurator = new Configurator('first', 'First');
        $firstProduct->attachConfigurator($configurator);

        try {
            $firstProduct->attachConfigurator(new Configurator('second', 'Second'));
            self::fail('A second configurator must be rejected.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('bereits einen Konfigurator', $exception->getMessage());
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('nicht einem anderen Produkt');
        $configurator->assignToProduct($secondProduct);
    }

    public function testMigrationPreservesLegacyConfiguratorAndAddsUniqueGuard(): void
    {
        $migration = file_get_contents(__DIR__ . '/../../migrations/Version20260817183000.php');

        self::assertIsString($migration);
        self::assertStringContainsString("ADD product_kind VARCHAR(20) DEFAULT 'standard' NOT NULL", $migration);
        self::assertStringContainsString("SET p.product_kind = 'configurable'", $migration);
        self::assertStringContainsString('migration_duplicate_product_configurator', $migration);
        self::assertStringContainsString('UNIQ_CN_CONFIGURATOR_PRODUCT', $migration);
        self::assertStringNotContainsString('DELETE FROM cardnext_configurator', $migration);
    }

    public function testProductAdminAndStorefrontTemplatesRespectProductKind(): void
    {
        $form = file_get_contents(__DIR__ . '/../../src/Form/Extension/ProductTypeExtension.php');
        $action = file_get_contents(__DIR__ . '/../../templates/admin/cardnext/product/configurator_action.html.twig');
        $summary = file_get_contents(__DIR__ . '/../../templates/shop/product/configurable_summary.html.twig');
        $card = file_get_contents(__DIR__ . '/../../templates/shop/category/product_card.html.twig');

        self::assertStringContainsString('Konfigurationsprodukt', (string) $form);
        self::assertStringContainsString("'disabled' => true", (string) $form);
        self::assertStringContainsString('current_product.isConfigurable', (string) $action);
        self::assertStringContainsString('product.isConfigurable', (string) $summary);
        self::assertStringContainsString('@SyliusShop/product/show/content/info/summary.html.twig', (string) $summary);
        self::assertStringContainsString('Konfigurierbares Produkt', (string) $card);
    }

    private function configurableProduct(string $code): Product
    {
        $product = new Product();
        $product->setCode($code);
        $product->setProductKind(ProductKind::CONFIGURABLE);

        return $product;
    }
}
