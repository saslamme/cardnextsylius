<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Configurator\Configurator;
use App\Entity\Product\Product;
use App\Repository\Configurator\ConfiguratorRepository;
use App\Twig\Component\ConfiguratorProductComponent;
use PHPUnit\Framework\TestCase;

final class ConfiguratorStorefrontTest extends TestCase
{
    public function testProductWithoutConfiguratorDoesNotExposeStorefrontModel(): void
    {
        $product = new Product();
        $repository = $this->createMock(ConfiguratorRepository::class);
        $repository->expects(self::once())->method('findEnabledByProduct')->with($product)->willReturn(null);

        $component = new ConfiguratorProductComponent($repository);
        $component->product = $product;
        $component->postMount();

        self::assertNull($component->configurator);
    }

    public function testEnabledConfiguratorIsExposedToStorefrontTemplate(): void
    {
        $product = new Product();
        $configurator = new Configurator('generic', 'Generic configurator');
        $configurator->setProduct($product);
        $repository = $this->createMock(ConfiguratorRepository::class);
        $repository->method('findEnabledByProduct')->with($product)->willReturn($configurator);

        $component = new ConfiguratorProductComponent($repository);
        $component->product = $product;
        $component->postMount();

        self::assertSame($configurator, $component->configurator);
    }

    public function testTemplateGuardsUiAndSupportsEveryFieldType(): void
    {
        $template = file_get_contents(__DIR__ . '/../../templates/shop/configurator/product.html.twig');

        self::assertIsString($template);
        self::assertStringStartsWith('{% if configurator %}', trim($template));
        foreach (['single_choice', 'multiple_choice', 'boolean', 'integer', 'decimal', 'text', 'quantity', 'upload'] as $type) {
            self::assertStringContainsString($type, $template);
        }
        self::assertStringContainsString('Dateiupload wird in einem späteren Schritt ergänzt.', $template);
        self::assertStringNotContainsString('In den Warenkorb', $template);
    }

    public function testCalculateControllerUsesServerContextsAndCoreCalculator(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../src/Controller/Shop/ConfiguratorCalculateController.php');

        self::assertIsString($controller);
        self::assertStringContainsString('$this->channelContext->getChannel()', $controller);
        self::assertStringContainsString('$this->currencyContext->getCurrencyCode()', $controller);
        self::assertStringContainsString('$this->calculator->calculate(', $controller);
        self::assertStringNotContainsString('$payload[\'currency', $controller);
        self::assertStringNotContainsString('$payload[\'channel', $controller);
        self::assertStringNotContainsString('$payload[\'price', $controller);
    }

    public function testClientOnlySendsQuantityAndSelectionsAndDoesNotCalculatePrices(): void
    {
        $client = file_get_contents(__DIR__ . '/../../assets/shop/configurator.js');

        self::assertIsString($client);
        self::assertStringContainsString('JSON.stringify({quantity, selections})', $client);
        self::assertStringNotContainsString('channelCode', $client);
        self::assertStringNotContainsString('currencyCode:', $client);
        self::assertStringNotContainsString('unitPrice', $client);
    }
}
