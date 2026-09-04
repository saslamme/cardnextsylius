<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Form\Cms\CmsProductSelectionType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\RouterInterface;

final class CmsProductSelectionRoutingTest extends KernelTestCase
{
    public function testEntityAutocompleteRouteIsRegisteredBelowTheAdminPrefix(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $route = $router->getRouteCollection()->get('ux_entity_autocomplete');

        self::assertNotNull($route);
        self::assertSame('/admin/autocomplete/{alias}', $route->getPath());
        self::assertSame(
            'ux_entity_autocomplete',
            $router->match('/admin/autocomplete/cms_product_selection')['_route'],
        );
    }

    public function testProductSelectionFieldCanBeBuiltWithoutAMissingRouteException(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');
        $form = $formFactory->create(CmsProductSelectionType::class);

        self::assertSame(
            '/admin/autocomplete/cms_product_selection',
            $form->getConfig()->getAttribute('autocomplete_url'),
        );
        $form->createView();
    }
}
