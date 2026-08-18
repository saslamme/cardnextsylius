<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Configurator\Configurator;
use App\Entity\Product\Product;
use App\Enum\Product\ProductKind;
use App\Service\Configurator\ConfiguratorAggregateDeleter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ConfiguratorAdminDeleteTest extends TestCase
{
    public function testConfiguredProductIsRemovedAsOneTransactionalAggregate(): void
    {
        $product = new Product();
        $product->setProductKind(ProductKind::CONFIGURABLE);
        $configurator = new Configurator('cards', 'Cards');
        $product->attachConfigurator($configurator);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('wrapInTransaction')->willReturnCallback(static function (callable $operation) use ($entityManager): void {
            $operation($entityManager);
        });
        $entityManager->expects(self::once())->method('remove')->with($product);

        (new ConfiguratorAggregateDeleter($entityManager))->delete($configurator);
    }

    public function testStandaloneConfiguratorIsRemovedTransactionally(): void
    {
        $configurator = new Configurator('legacy', 'Legacy');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static function (callable $operation) use ($entityManager): void {
            $operation($entityManager);
        });
        $entityManager->expects(self::once())->method('remove')->with($configurator);

        (new ConfiguratorAggregateDeleter($entityManager))->delete($configurator);
    }

    public function testIndexContainsPostOnlyCsrfProtectedDeleteAction(): void
    {
        $template = file_get_contents(__DIR__ . '/../../templates/admin/cardnext/configurator/index.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../src/Controller/Admin/ConfiguratorAdminController.php');

        self::assertIsString($template);
        self::assertIsString($controller);
        self::assertStringContainsString('method="post"', $template);
        self::assertStringContainsString("csrf_token('configurator-delete-'~c.id)", $template);
        self::assertStringContainsString("methods: ['POST']", $controller);
        self::assertStringContainsString('createAccessDeniedException', $controller);
    }
}
