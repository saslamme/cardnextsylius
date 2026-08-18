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
        self::assertFalse(method_exists($configurator, 'getProduct'));
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
}
