<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class PublicPathRoutingArchitectureTest extends KernelTestCase
{
    public function testSingleSegmentCatalogCollisionRulesAreExplicitAndCmsIsTheFallback(): void
    {
        $controller = (string) file_get_contents(__DIR__.'/../../src/Controller/Shop/PublicSlugController.php');
        $taxon = strpos($controller, '$this->taxonRepository->findOneBySlug');
        $product = strpos($controller, '$this->productRepository->findOneByChannelAndSlug');
        $cms = strpos($controller, '$this->cmsStorefrontResolver->resolve');
        $notFound = strrpos($controller, 'throw new NotFoundHttpException');

        self::assertIsInt($taxon);
        self::assertIsInt($product);
        self::assertIsInt($cms);
        self::assertIsInt($notFound);
        self::assertLessThan($product, $taxon, 'An existing taxon must win over product and CMS collisions.');
        self::assertLessThan($cms, $product, 'An existing product must win over a CMS collision.');
        self::assertLessThan($notFound, $cms, 'CMS pages and redirects must be tried before the final 404.');
    }

    public function testSingleSegmentPathUsesThePublicSlugResolver(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        self::assertSame('sylius_shop_product_index', $router->match('/support')['_route']);
    }

    public function testMultiSegmentCmsAndConfiguratorPathsUseTheDeterministicFallback(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        foreach (['/service/support', '/unternehmen/ueber-uns', '/plastikkarten/plastikkarten-bedrucken'] as $path) {
            $match = $router->match($path);
            self::assertSame('cardnext_shop_configurator_page', $match['_route']);
            self::assertSame(ltrim($path, '/'), $match['configuratorPath']);
        }

        $controller = (string) file_get_contents(__DIR__.'/../../src/Controller/Shop/ConfiguratorPageController.php');
        self::assertStringContainsString('$this->resolver->resolve($configuratorPath', $controller);
        self::assertStringContainsString('$this->cmsStorefrontResolver->resolve($configuratorPath)', $controller);
    }
}
