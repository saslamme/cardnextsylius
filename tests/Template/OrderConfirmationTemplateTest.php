<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class OrderConfirmationTemplateTest extends TestCase
{
    public function testCustomerAndOrderDataAndLinkAttributesAreEscaped(): void
    {
        $projectLoader = new FilesystemLoader(dirname(__DIR__, 2).'/templates');
        $projectLoader->addPath(dirname(__DIR__, 2).'/templates/bundles/SyliusShopBundle/email', 'SyliusCore');
        $loader = new ChainLoader([
            new ArrayLoader(['@SyliusCore/Email/orderConfirmation.html.twig' => '{% block body %}{% endblock %}']),
            $projectLoader,
        ]);
        $twig = new Environment($loader, ['autoescape' => 'html']);
        $twig->addFunction(new TwigFunction('sylius_bundle_loaded_checker', static fn (): bool => true));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/'.$route.'?next=" onclick="alert(1)'));
        $twig->addFunction(new TwigFunction('sylius_channel_url', static fn (string $path): string => 'https://shop.example'.$path));
        $twig->addFilter(new TwigFilter('trans', static fn (string $value): string => $value));
        $twig->addFilter(new TwigFilter('sylius_format_money', static fn (int $value): string => (string) $value));
        $twig->addFilter(new TwigFilter('sylius_country_name', static fn (string $value): string => $value));

        $injection = '<script>alert("customer")</script>';
        $address = (object) [
            'company' => $injection,
            'firstName' => $injection,
            'lastName' => $injection,
            'street' => $injection,
            'postcode' => $injection,
            'city' => $injection,
            'countryCode' => $injection,
        ];
        $item = (object) [
            'productName' => $injection,
            'variantName' => $injection.' variant',
            'variant' => (object) ['code' => $injection],
            'quantity' => 1,
            'total' => 100,
        ];
        $order = (object) [
            'tokenValue' => 'token',
            'billingAddress' => $address,
            'shippingAddress' => $address,
            'shipments' => [(object) ['method' => (object) ['name' => $injection]]],
            'payments' => [(object) ['method' => (object) ['name' => $injection]]],
            'number' => $injection,
            'items' => [$item],
            'itemsTotal' => 100,
            'shippingTotal' => 0,
            'taxTotal' => 0,
            'total' => 100,
            'currencyCode' => 'EUR',
        ];

        $html = $twig->render('bundles/SyliusShopBundle/email/order_confirmation.html.twig', [
            'localeCode' => 'de_DE',
            'channel' => new \stdClass(),
            'order' => $order,
        ]);

        self::assertStringNotContainsString($injection, $html);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;customer&quot;)&lt;/script&gt;', $html);
        self::assertStringNotContainsString(' onclick="alert(1)', $html);
        self::assertStringContainsString('&quot;&#x20;onclick&#x3D;&quot;alert&#x28;1&#x29;', $html);
    }
}
