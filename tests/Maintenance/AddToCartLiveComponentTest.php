<?php

declare(strict_types=1);

namespace App\Tests\Maintenance;

use App\Twig\Component\Product\AddToCartFormComponent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AddToCartLiveComponentTest extends TestCase
{
    public function testReturnRouteSurvivesLiveComponentRerenders(): void
    {
        $stack = new RequestStack();
        $request = Request::create('/printer');
        $request->attributes->add([
            '_route' => 'sylius_shop_product_show',
            '_route_params' => ['slug' => 'printer'],
        ]);
        $stack->push($request);
        $component = $this->componentFor($stack);

        $component->postMount();

        self::assertSame('sylius_shop_product_show', $component->routeName);
        self::assertSame(['slug' => 'printer', 'cnCart' => 'open'], $component->routeParameters);

        $stack->pop();
        $request = Request::create('/_components/sylius_shop:product:add_to_cart_form');
        $request->attributes->set('_route', 'ux_live_component');
        $stack->push($request);

        $component->postMount();

        self::assertSame('sylius_shop_product_show', $component->routeName);
        self::assertSame(['slug' => 'printer', 'cnCart' => 'open'], $component->routeParameters);
    }

    public function testTemplateKeepsLiveBindingsAndMaintenanceFieldsInsideMainForm(): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../../templates/bundles/SyliusShopBundle/product/show/content/info/summary/add_to_cart.html.twig');
        $maintenance = (string) file_get_contents(__DIR__ . '/../../templates/shop/product/maintenance_offers.html.twig');

        self::assertStringContainsString('<div class="cn-purchase position-relative" {{ attributes }}>', $template);
        self::assertStringContainsString("'data-action': 'live#action:prevent live#\$render'", $template);
        self::assertStringContainsString("'data-live-action-param': 'addToCart'", $template);
        self::assertStringContainsString("'data-live-route-name-param': routeName", $template);
        self::assertStringContainsString("'data-live-route-parameters-param': routeParameters|json_encode", $template);

        $mainFormStart = strpos($template, '{{ form_start(form');
        $maintenanceInclude = strpos($template, "{% include 'shop/product/maintenance_offers.html.twig'");
        $mainFormEnd = strpos($template, "{{ form_end(form, {'render_rest': false}) }}");
        self::assertIsInt($mainFormStart);
        self::assertIsInt($maintenanceInclude);
        self::assertIsInt($mainFormEnd);
        self::assertLessThan($maintenanceInclude, $mainFormStart);
        self::assertLessThan($mainFormEnd, $maintenanceInclude);
        self::assertStringNotContainsString('<form', $maintenance);
    }

    public function testMaintenanceBlockIsOnlyRenderedWhenOffersExist(): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../../templates/bundles/SyliusShopBundle/product/show/content/info/summary/add_to_cart.html.twig');

        self::assertStringContainsString('{% if maintenance_offers is not empty and form.maintenanceVariant is defined %}', $template);
    }

    public function testSingleOfferUsesAnUnselectedCheckboxWithoutANoneRow(): void
    {
        $maintenance = (string) file_get_contents(__DIR__ . '/../../templates/shop/product/maintenance_offers.html.twig');

        self::assertStringContainsString("type=\"{{ offers|length == 1 ? 'checkbox' : 'radio' }}\"", $maintenance);
        self::assertStringContainsString('{% if offers|length > 1 %}', $maintenance);
        self::assertStringNotContainsString('type="checkbox" checked', $maintenance);
        self::assertStringContainsString('value="{{ offer.variant.id }}"', $maintenance);
    }

    public function testMultipleOffersKeepRadioSemanticsAndAnExplicitEmptyChoice(): void
    {
        $maintenance = (string) file_get_contents(__DIR__ . '/../../templates/shop/product/maintenance_offers.html.twig');

        self::assertStringContainsString('type="radio" name="{{ field.vars.full_name }}" value="" checked', $maintenance);
        self::assertStringContainsString("type=\"{{ offers|length == 1 ? 'checkbox' : 'radio' }}\"", $maintenance);
        self::assertSame(1, substr_count($maintenance, 'name="{{ field.vars.full_name }}" value=""'));
    }

    public function testMaintenancePriceUsesTheRequestLocaleWithSyliusMoneyFormatting(): void
    {
        $maintenance = (string) file_get_contents(__DIR__ . '/../../templates/shop/product/maintenance_offers.html.twig');

        self::assertStringContainsString(
            'offer.price|sylius_format_money(offer.currencyCode, app.request.locale)',
            $maintenance,
        );
    }

    private function componentFor(RequestStack $stack): AddToCartFormComponent
    {
        $component = (new \ReflectionClass(AddToCartFormComponent::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(AddToCartFormComponent::class, 'requestStack'))->setValue($component, $stack);

        return $component;
    }
}
