<?php

declare(strict_types=1);

namespace App\Tests\International;

use App\EventSubscriber\MarketSeoSubscriber;
use App\International\CardnextMarketRegistry;
use App\International\MarketUrlResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
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
}
