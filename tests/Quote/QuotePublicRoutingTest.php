<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Controller\Shop\QuotePublicController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

final class QuotePublicRoutingTest extends KernelTestCase
{
    private const TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testPublicQuoteRoutesWinOverConfiguratorCatchAll(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        $view = $router->match('/de_DE/angebot/AG-2026-00003/v1/'.self::TOKEN);
        self::assertSame('cardnext_shop_quote_public', $view['_route']);
        self::assertStringStartsWith(QuotePublicController::class.'::', $view['_controller']);
        self::assertSame(
            'cardnext_shop_quote_public_pdf',
            $router->match('/de_DE/angebot/AG-2026-00003/v1/'.self::TOKEN.'/pdf')['_route'],
        );
        $router->getContext()->setMethod('POST');
        self::assertSame(
            'cardnext_shop_quote_accept',
            $router->match('/de_DE/angebot/AG-2026-00003/v1/'.self::TOKEN.'/annehmen')['_route'],
        );
        self::assertSame(
            'cardnext_shop_quote_reject',
            $router->match('/de_DE/angebot/AG-2026-00003/v1/'.self::TOKEN.'/ablehnen')['_route'],
        );
    }

    public function testMalformedTokenDoesNotFallThroughToConfigurator(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        $this->expectException(ResourceNotFoundException::class);
        $router->match('/de_DE/angebot/AG-2026-00003/v1/not-a-token');
    }

    public function testConfiguratorRouteStillMatchesNonReservedPaths(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');

        self::assertSame('cardnext_shop_configurator_page', $router->match('/de_DE/card-reader/configure')['_route']);
    }
}
