<?php

declare(strict_types=1);

namespace App\Tests\Order;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ReorderArchitectureTest extends TestCase
{
    public function testReorderEndpointAndAccountFormArePostAndCsrfProtected(): void
    {
        $controller = file_get_contents(__DIR__.'/../../src/Controller/Shop/ReorderController.php');
        $template = file_get_contents(__DIR__.'/../../templates/bundles/SyliusShopBundle/account/order/show/content/main/summary.html.twig');

        self::assertIsString($controller);
        self::assertStringContainsString("methods: ['POST']", $controller);
        self::assertStringContainsString("'reorder_'.\$order->getId()", $controller);
        self::assertStringContainsString('o.customer = :customer', $controller);
        self::assertStringContainsString('o.channel = :channel', $controller);
        self::assertStringContainsString('o.checkoutCompletedAt IS NOT NULL', $controller);
        self::assertIsString($template);
        self::assertStringContainsString('<form method="post"', $template);
        self::assertStringContainsString("csrf_token('reorder_' ~ order.id)", $template);
    }

    public function testCurrentPricingAndSharedConfiguratorBuilderAreUsed(): void
    {
        $service = file_get_contents(__DIR__.'/../../src/Service/Order/ReorderService.php');
        $controller = file_get_contents(__DIR__.'/../../src/Controller/Shop/ConfiguredCartController.php');

        self::assertIsString($service);
        self::assertStringContainsString('$this->orderProcessor->process($cart)', $service);
        self::assertStringNotContainsString('getUnitPrice()', $service);
        self::assertStringNotContainsString('setUnitPrice(', $service);
        self::assertStringContainsString('ConfiguredCartItemFactory', $service);
        self::assertIsString($controller);
        self::assertStringContainsString('ConfiguredCartItemFactory', $controller);
        self::assertStringNotContainsString('function calculateItem', $controller);
    }

    public function testReorderPlansCombinedRegularQuantitiesBeforeMutatingTheCart(): void
    {
        $service = file_get_contents(__DIR__.'/../../src/Service/Order/ReorderService.php');

        self::assertIsString($service);
        self::assertStringContainsString('$plannedQuantities', $service);
        self::assertStringContainsString('$oldItem->getQuantity() +', $service);
        self::assertStringContainsString('$plannedQuantities[$variantKey] = $quantity', $service);
        self::assertLessThan(strpos($service, '$this->addRegularItem($cart, $oldItem)'), strpos($service, '$validBundles = []'));
    }

    public function testHistoricalBundleMarkersAndCurrentDefinitionsAreValidated(): void
    {
        $service = file_get_contents(__DIR__.'/../../src/Service/Order/ReorderService.php');
        $services = Yaml::parseFile(__DIR__.'/../../config/services.yaml');

        self::assertIsString($service);
        self::assertStringContainsString('if ($oldItem->getBundleGroupKey() !== null)', $service);
        self::assertStringContainsString('count($mainItems) !== 1', $service);
        self::assertStringContainsString('$mainVariant->getProduct() !== $mainProduct', $service);
        self::assertStringContainsString('$definition->getQuantity() * $bundleQuantity', $service);
        self::assertStringContainsString('$definition->isEnabled()', $service);
        self::assertSame(
            '@sylius.custom_factory.order_item',
            $services['services']['App\\Service\\Order\\ReorderService']['arguments']['$cartItemFactory'] ?? null,
        );
    }

    public function testReorderTranslationKeysStayAtTheExpectedPathInEveryShopLocale(): void
    {
        foreach (['de', 'de_AT', 'en', 'da_DK', 'es_ES', 'it_IT', 'nl_NL', 'sv_SE'] as $locale) {
            $messages = Yaml::parseFile(__DIR__.'/../../translations/messages.'.$locale.'.yaml');
            self::assertIsArray($messages);
            foreach (['button', 'success', 'partial', 'none', 'protection_notice', 'configured_unavailable', 'bundle_unavailable'] as $key) {
                self::assertArrayHasKey('cardnext.reorder.'.$key, $messages, $locale.' is missing '.$key);
            }
        }
    }
}
