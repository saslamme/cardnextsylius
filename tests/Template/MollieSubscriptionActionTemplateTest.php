<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class MollieSubscriptionActionTemplateTest extends TestCase
{
    private const TEMPLATE = 'bundles/SyliusMolliePlugin/shop/account/order/grid/action/cancel_mollie_subscription.html.twig';

    #[DataProvider('missingTokenProvider')]
    public function testSubscriptionComponentIsNotRenderedWithoutAnOrderToken(?string $tokenValue): void
    {
        $componentCalls = [];
        $twig = $this->twig($componentCalls);

        $html = $twig->render(self::TEMPLATE, [
            'data' => (object) ['tokenValue' => $tokenValue],
        ]);

        self::assertSame([], $componentCalls);
        self::assertSame('', trim($html));
    }

    /** @return iterable<string, array{?string}> */
    public static function missingTokenProvider(): iterable
    {
        yield 'null token from a legacy or custom-created order' => [null];
        yield 'empty token' => [''];
    }

    public function testSubscriptionComponentKeepsRenderingForAnOrderWithAToken(): void
    {
        $componentCalls = [];
        $twig = $this->twig($componentCalls);
        $order = (object) ['tokenValue' => 'real-order-token'];

        $html = $twig->render(self::TEMPLATE, ['data' => $order]);

        self::assertSame([[
            'name' => 'sylius_mollie:shop:order:cancel_subscription',
            'properties' => [
                'order' => $order,
                'template' => '@SyliusMolliePlugin/shop/component/cancel_subscription.html.twig',
            ],
        ]], $componentCalls);
        self::assertStringContainsString('mollie-subscription-action', $html);
    }

    /**
     * @param list<array{name: string, properties: array<string, mixed>}> $componentCalls
     */
    private function twig(array &$componentCalls): Environment
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'));
        $twig->addFunction(new TwigFunction(
            'component',
            static function (string $name, array $properties) use (&$componentCalls): string {
                $componentCalls[] = ['name' => $name, 'properties' => $properties];

                return 'mollie-subscription-action';
            },
        ));

        return $twig;
    }
}
