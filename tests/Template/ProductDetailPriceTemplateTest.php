<?php

declare(strict_types=1);

namespace App\Tests\Template;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPriceHistoryConfig;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Node\Node;
use Twig\Node\TextNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ProductDetailPriceTemplateTest extends TestCase
{
    private const TEMPLATE = 'bundles/SyliusShopBundle/product/show/content/info/summary/prices.html.twig';

    #[DataProvider('localeProvider')]
    public function testPriceComponentRendersAProductVariantWithAChannelPrice(string $locale): void
    {
        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setChannelPriceHistoryConfig(new ChannelPriceHistoryConfig());

        $product = new Product();
        $product->setCurrentLocale($locale);
        $product->setFallbackLocale('de_DE');
        $product->setName('Regression product');

        $variant = new ProductVariant();
        $product->addVariant($variant);
        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode('WEB');
        $channelPricing->setPrice(12900);
        $variant->addChannelPricing($channelPricing);

        self::assertSame(12900, $variant->getChannelPricingForChannel($channel)?->getPrice());

        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addTokenParser(new PriceHookTokenParser());
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->addFilter(new TwigFilter('sylius_has_discount', static fn (): bool => false));
        $twig->addFilter(new TwigFilter('sylius_has_lowest_price', static fn (): bool => false));
        $twig->addFunction(new TwigFunction('cardnext_b2b_variant_tiers', static fn (): array => []));
        $twig->addFunction(new TwigFunction('sylius_inventory_is_available', static fn (): bool => true));
        $twig->addFunction(new TwigFunction('sylius_test_html_attribute', static fn (): string => '', ['is_safe' => ['html']]));

        $html = $twig->render(self::TEMPLATE, [
            'hookable_metadata' => (object) ['context' => (object) ['variant' => $variant]],
            'sylius' => (object) ['channel' => $channel, 'localeCode' => $locale],
        ]);

        self::assertStringContainsString('cn-price-panel', $html);
        self::assertStringContainsString('data-rendered-price="12900"', $html);
        self::assertStringContainsString('cardnext.storefront.product_detail.pricing.your_price', $html);
        self::assertStringContainsString('cardnext.storefront.product_card.available', $html);
    }

    /** @return iterable<string, array{string}> */
    public static function localeProvider(): iterable
    {
        yield 'German storefront' => ['de_DE'];
        yield 'Swedish storefront' => ['sv_SE'];
    }
}

final class PriceHookTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $this->parser->getExpressionParser()->parseExpression();

        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            $this->parser->getExpressionParser()->parseMultitargetExpression();
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new TextNode('<span data-rendered-price="12900"></span>', $token->getLine());
    }

    public function getTag(): string
    {
        return 'hook';
    }
}
