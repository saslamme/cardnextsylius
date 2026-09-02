<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product\Product;
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

        return $this->router->generate('sylius_shop_product_show', ['slug' => $translation->getSlug()], $referenceType);
    }
}
