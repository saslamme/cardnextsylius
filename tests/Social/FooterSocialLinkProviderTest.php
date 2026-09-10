<?php

declare(strict_types=1);

namespace App\Tests\Social;

use App\Entity\Channel\Channel;
use App\Social\FooterSocialLinkProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

final class FooterSocialLinkProviderTest extends TestCase
{
    #[DataProvider('urlCases')]
    public function testItProvidesOnlyConfiguredLinksInStableOrder(array $urls, array $expectedCodes): void
    {
        $channel = new Channel();
        foreach ($urls as $network => $url) {
            $channel->{'set' . ucfirst($network) . 'Url'}($url);
        }

        $links = $this->provider($channel)->provide();
        self::assertSame($expectedCodes, array_column($links, 'code'));
    }

    public static function urlCases(): iterable
    {
        yield 'all' => [['instagram' => 'https://i.example/a', 'facebook' => 'https://f.example/a', 'linkedin' => 'https://l.example/a', 'youtube' => 'https://y.example/a'], ['instagram', 'facebook', 'linkedin', 'youtube']];
        yield 'instagram' => [['instagram' => 'https://i.example/a'], ['instagram']];
        yield 'instagram and linkedin' => [['instagram' => 'https://i.example/a', 'linkedin' => 'https://l.example/a'], ['instagram', 'linkedin']];
        yield 'none' => [[], []];
    }

    public function testItTrimsUrlsAndTreatsEmptyStringsAsNull(): void
    {
        $channel = new Channel();
        $channel->setInstagramUrl('  https://example.com/profile  ');
        $channel->setFacebookUrl('  ');
        self::assertSame('https://example.com/profile', $channel->getInstagramUrl());
        self::assertNull($channel->getFacebookUrl());
        self::assertSame('https://example.com/profile', $this->provider($channel)->provide()[0]['url']);
    }

    public function testItSafelyIgnoresForeignChannelImplementations(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        self::assertSame([], $this->provider($channel)->provide());
    }

    private function provider(ChannelInterface $channel): FooterSocialLinkProvider
    {
        $context = $this->createMock(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);

        return new FooterSocialLinkProvider($context);
    }
}
