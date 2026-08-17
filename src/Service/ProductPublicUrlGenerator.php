<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product\Product;
use App\Entity\Product\ProductTranslation;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ProductPublicUrlGenerator
{
    public function __construct(
        private UrlGeneratorInterface $router,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function generate(Product $product, ?string $locale = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $locale ??= $this->localeContext->getLocaleCode();
        $translation = $product->getTranslation($locale);
        if ($product->isConfigurable() && $translation instanceof ProductTranslation && $translation->getConfiguratorPath() !== null) {
            return $this->router->generate('cardnext_shop_configurator_page', ['_locale' => $locale, 'configuratorPath' => $translation->getConfiguratorPath()], $referenceType);
        }

        return $this->router->generate('sylius_shop_product_show', ['_locale' => $locale, 'slug' => $translation->getSlug()], $referenceType);
    }
}
