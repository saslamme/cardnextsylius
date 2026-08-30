<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Quote\Quote;
use App\Service\Quote\QuotePublicUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class QuotePublicUrlGeneratorTest extends TestCase
{
    /** @dataProvider channelUrls */
    public function testUsesCanonicalChannelHost(string $channel, string $baseUrl): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with('cardnext_shop_quote_public', self::callback(static fn (array $parameters): bool => $parameters['token'] === str_repeat('a', 64)), UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/de_DE/angebot/AG-2026-00003/v1/'.str_repeat('a', 64));

        $quote = new Quote();
        $quote->setChannelCode($channel);
        $quote->setLocaleCode('de_DE');
        $quote->setNumber('AG-2026-00003');
        $quote->setVersion(1);

        $generator = new QuotePublicUrlGenerator($router, [
            'CARDNEXT_DE' => 'https://www.cardnext.de',
            'CARDNEXT_AT' => 'https://at.cardnext.de',
        ]);

        self::assertSame($baseUrl.'/de_DE/angebot/AG-2026-00003/v1/'.str_repeat('a', 64), $generator->view($quote, str_repeat('a', 64)));
    }

    /** @return iterable<string, array{string, string}> */
    public static function channelUrls(): iterable
    {
        yield 'German shop' => ['CARDNEXT_DE', 'https://www.cardnext.de'];
        yield 'Austrian shop' => ['CARDNEXT_AT', 'https://at.cardnext.de'];
    }
}
