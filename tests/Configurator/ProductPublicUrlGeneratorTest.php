<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Product\Product;
use App\Entity\Product\ProductTranslation;
use App\Enum\Product\ProductKind;
use App\Service\ProductPublicUrlGenerator;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductPublicUrlGeneratorTest extends TestCase
{
    public function testStandardProductUsesTheLocaleContext(): void
    {
        $product = $this->product('de_DE', 'standardprodukt');
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with('sylius_shop_product_show', ['_locale' => 'de_DE', 'slug' => 'standardprodukt'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/de_DE/products/standardprodukt');

        $generator = new ProductPublicUrlGenerator($router, $this->localeContext('de_DE'));

        self::assertSame('/de_DE/products/standardprodukt', $generator->generate($product));
        self::assertFalse(method_exists($product, 'getCurrentLocale'));
    }

    public function testConfigurableProductPreservesItsNestedPathAndReferenceType(): void
    {
        $product = $this->product('de_DE', 'plastikkarten', 'plastikkarten/plastikkarten-bedrucken');
        $product->setProductKind(ProductKind::CONFIGURABLE);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with(
                'cardnext_shop_configurator_page',
                ['_locale' => 'de_DE', 'configuratorPath' => 'plastikkarten/plastikkarten-bedrucken'],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn('https://example.com/de_DE/plastikkarten/plastikkarten-bedrucken');

        $generator = new ProductPublicUrlGenerator($router, $this->localeContext('de_DE'));

        self::assertSame(
            'https://example.com/de_DE/plastikkarten/plastikkarten-bedrucken',
            $generator->generate($product, referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }

    public function testExplicitLocaleOverridesTheLocaleContext(): void
    {
        $product = $this->product('en_GB', 'english-product');
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with('sylius_shop_product_show', ['_locale' => 'en_GB', 'slug' => 'english-product'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/en_GB/products/english-product');
        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->expects(self::never())->method('getLocaleCode');

        $generator = new ProductPublicUrlGenerator($router, $localeContext);

        self::assertSame('/en_GB/products/english-product', $generator->generate($product, 'en_GB'));
    }

    private function product(string $locale, string $slug, ?string $configuratorPath = null): Product
    {
        $product = new Product();
        $translation = new ProductTranslation();
        $translation->setLocale($locale);
        $translation->setSlug($slug);
        $translation->setConfiguratorPath($configuratorPath);
        $product->addTranslation($translation);

        return $product;
    }

    private function localeContext(string $locale): LocaleContextInterface
    {
        $context = $this->createMock(LocaleContextInterface::class);
        $context->method('getLocaleCode')->willReturn($locale);

        return $context;
    }
}
