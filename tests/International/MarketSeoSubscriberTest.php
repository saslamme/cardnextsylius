<?php

declare(strict_types=1);

namespace App\Tests\International;

use App\EventSubscriber\MarketSeoSubscriber;
use App\International\CardnextMarketRegistry;
use App\International\MarketUrlResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MarketSeoSubscriberTest extends TestCase
{
    public function testUnknownTestHostDoesNotFallBackToAMarketChannel(): void
    {
        $channels = $this->createMock(ChannelContextInterface::class);
        $channels->expects(self::never())->method('getChannel');
        $resolver = new MarketUrlResolver(
            new CardnextMarketRegistry(),
            $channels,
            $this->createMock(RepositoryInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
        );
        $subscriber = new MarketSeoSubscriber($resolver, new CardnextMarketRegistry());
        $request = Request::create('http://unknown.example.test/');
        $response = new Response('<html><head></head><body></body></html>', headers: ['Content-Type' => 'text/html']);
        $event = new ResponseEvent(
            $this->createMock(KernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $subscriber->addMarketLinks($event);

        self::assertSame('<html><head></head><body></body></html>', $response->getContent());
    }

    public function testHomepageReceivesOneCanonicalAndAllMarketAlternates(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('CARDNEXT_ES');
        $channels = $this->createMock(ChannelContextInterface::class);
        $channels->method('getChannel')->willReturn($channel);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => '/' . $parameters['_locale'] . '/');
        $resolver = new MarketUrlResolver(new CardnextMarketRegistry(), $channels, $this->createMock(RepositoryInterface::class), $router);
        $subscriber = new MarketSeoSubscriber($resolver, new CardnextMarketRegistry());
        $request = Request::create('https://es.cardnext.de/es_ES/');
        $request->attributes->set('_route', 'sylius_shop_homepage');
        $request->attributes->set('_route_params', ['_locale' => 'es_ES']);
        $response = new Response('<html><head><link rel="canonical" href="https://wrong.example/"></head><body></body></html>', headers: ['Content-Type' => 'text/html']);
        $event = new ResponseEvent($this->createMock(KernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->addMarketLinks($event);

        $content = (string) $response->getContent();
        self::assertSame(1, substr_count($content, 'rel="canonical"'));
        self::assertSame(7, substr_count($content, 'rel="alternate"'));
        self::assertStringContainsString('href="https://es.cardnext.de/es_ES/"', $content);
        self::assertStringContainsString('hreflang="sv-SE" href="https://se.cardnext.de/sv_SE/"', $content);
        self::assertStringNotContainsString('x-default', $content);
    }
}
