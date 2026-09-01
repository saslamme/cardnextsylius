<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use App\Seo\ChannelCanonicalUrlResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;

final class ChannelCanonicalUrlResolverTest extends TestCase
{
    #[DataProvider('channels')]
    public function testCanonicalUsesResolvedChannelRatherThanInboundHost(string $hostname): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('isEnabled')->willReturn(true);
        $channel->method('getHostname')->willReturn($hostname);
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);

        $request = Request::create('http://arbitrary-alias.invalid/de_DE/products?utm_source=test&page=2');

        self::assertSame('https://' . $hostname . '/de_DE/products', (new ChannelCanonicalUrlResolver($context))->resolve($request));
    }

    /** @return iterable<string, array{string}> */
    public static function channels(): iterable
    {
        yield 'Cardnext' => ['www.cardnext.de'];
        yield 'Identible' => ['identible.cardnext.de'];
        yield 'Inplastor' => ['inplastor.cardnext.de'];
    }

    public function testUnknownChannelDoesNotFallBackToCardnext(): void
    {
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willThrowException(new \RuntimeException('No channel'));

        self::assertNull((new ChannelCanonicalUrlResolver($context))->resolve(Request::create('https://unknown.invalid/')));
    }
}
