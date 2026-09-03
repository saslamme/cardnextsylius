<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Cms\CmsStorefrontResolver;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicSlugController
{
    private const HIDDEN_ROOT_TAXON_CODE = 'CARDNEXT_PRODUCTS';

    /**
     * @param TaxonRepositoryInterface<\App\Entity\Taxonomy\Taxon> $taxonRepository
     * @param ProductRepositoryInterface<\App\Entity\Product\Product> $productRepository
     */
    public function __construct(
        private TaxonRepositoryInterface $taxonRepository,
        private ProductRepositoryInterface $productRepository,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        #[Autowire(service: 'sylius.controller.product')]
        private ResourceController $productController,
        private CmsStorefrontResolver $cmsStorefrontResolver,
    ) {
    }

    public function __invoke(Request $request, string $slug): Response
    {
        $locale = $this->localeContext->getLocaleCode();
        $channel = $this->channelContext->getChannel();
        $taxon = $this->taxonRepository->findOneBySlug($slug, $locale);

        if ($taxon !== null && $taxon->getCode() !== self::HIDDEN_ROOT_TAXON_CODE) {
            // @phpstan-ignore method.notFound
            $menuTaxon = $channel->getMenuTaxon();
            $taxonRoot = $taxon->getRoot();

            if ($menuTaxon !== null && $taxonRoot !== null && $taxonRoot->getCode() === $menuTaxon->getCode()) {
                $request->attributes->set('cardnext_taxon', $taxon);
                $request->attributes->set('_sylius', [
                    'template' => '@SyliusShop/product/index.html.twig',
                    'grid' => 'sylius_shop_product',
                ]);

                return $this->productController->indexAction($request);
            }
        }

        // @phpstan-ignore argument.type
        $product = $this->productRepository->findOneByChannelAndSlug($channel, $locale, $slug);
        if ($product !== null && (!$product instanceof \App\Entity\Product\Product || !$product->isAddonOnly())) {
            $request->attributes->set('cardnext_product', $product);
            $request->attributes->set('_sylius', [
                'template' => '@SyliusShop/product/show.html.twig',
                'repository' => [
                    'method' => 'findOneByChannelAndSlug',
                    'arguments' => [$channel, $locale, $slug],
                ],
            ]);

            return $this->productController->showAction($request);
        }

        $cmsResponse = $this->cmsStorefrontResolver->resolve($slug);
        if ($cmsResponse !== null) {
            return $cmsResponse;
        }

        throw new NotFoundHttpException(sprintf('No public taxon, product, or CMS page has slug "%s".', $slug));
    }
}
