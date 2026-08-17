<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Product\Product;
use App\Entity\Product\ProductTranslation;
use App\Enum\Product\ProductKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class ConfiguratorPathTest extends TestCase
{
    public function testPathIsStoredWithoutOuterSlashes(): void
    {
        $translation = $this->translation('/plastic-cards/plastic-card-printing/');

        self::assertSame('plastic-cards/plastic-card-printing', $translation->getConfiguratorPath());
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');
        $translation->validateConfiguratorPath($context);
    }

    #[DataProvider('invalidPaths')]
    public function testUnsafePathsAreRejected(string $path): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->willReturnSelf();
        $builder->expects(self::once())->method('addViolation');
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::once())->method('buildViolation')->willReturn($builder);

        $this->translation($path)->validateConfiguratorPath($context);
    }

    public static function invalidPaths(): iterable
    {
        yield ['https://foo.example/page'];
        yield ['foo?bar'];
        yield ['../foo'];
        yield ['foo//bar'];
        yield ['#anchor'];
        yield ['//foo'];
    }

    private function translation(string $path): ProductTranslation
    {
        $product = new Product();
        $product->setProductKind(ProductKind::CONFIGURABLE);
        $translation = new ProductTranslation();
        $translation->setLocale('en_GB');
        $translation->setTranslatable($product);
        $translation->setConfiguratorPath($path);

        return $translation;
    }
}
