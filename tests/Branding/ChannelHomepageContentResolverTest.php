<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use App\Content\ChannelHomepageContentResolver;
use App\Entity\Channel\Channel;
use App\Entity\Content\ChannelHomepageContent;
use App\Repository\Content\ChannelHomepageContentRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ChannelHomepageContentResolverTest extends TestCase
{
    #[DataProvider('brandFallbacks')]
    public function testMetaTitleFallbackUsesChannelBrand(string $brand): void
    {
        $channel = new Channel();
        $channel->setBrandName($brand);

        $title = $this->resolver($channel, 'de_DE', [])->resolve()->metaTitle;

        self::assertStringContainsString($brand, $title);
        if ($brand !== 'Cardnext') {
            self::assertStringNotContainsString('Cardnext', $title);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function brandFallbacks(): iterable
    {
        yield 'Cardnext' => ['Cardnext'];
        yield 'Identible' => ['Identible'];
        yield 'Inplastor' => ['Inplastor'];
    }

    public function testTranslationFallbackIsBrandAwareAndPartialOverridesWork(): void
    {
        $channel = new Channel();
        $channel->setBrandName('Identible');
        $content = new ChannelHomepageContent();
        $content->setChannel($channel);
        $content->setLocaleCode('de_DE');
        $content->setHeroTitle('Eigener Hero');

        $resolved = $this->resolver($channel, 'de_DE', [$content])->resolve();

        self::assertSame('Eigener Hero', $resolved->heroTitle);
        self::assertSame('Identible SEO', $resolved->metaTitle);
        self::assertSame($resolved->heroText, $resolved->metaDescription);
        self::assertSame('fallback:cardnext.storefront.homepage.hero.text', $resolved->heroText);
        self::assertSame('Warum Identible', $resolved->whyKicker);
        self::assertSame('fallback:cardnext.storefront.footer.description', $resolved->footerText);
        self::assertSame('cardnext/homepage/hero-card-printer.webp', $resolved->heroImagePath);
        self::assertSame('cardnext/homepage/service-consultation.webp', $resolved->introImagePath);
        self::assertSame('cardnext/homepage/support-advisor.webp', $resolved->ctaImagePath);
    }

    public function testPromoContentIsResolvedFromCurrentChannelAndLocale(): void
    {
        $channel = new Channel();
        $content = $this->content($channel, 'de_DE', 'Hero');
        $content->setPrinterGuideEnabled(true);
        $content->setPrinterGuideHeadline('Passenden Drucker finden');
        $content->setPrinterGuideImagePath('uploads/channel-homepage/advisor.webp');

        $resolved = $this->resolver($channel, 'de_DE', [$content])->resolve();

        self::assertTrue($resolved->printerGuidePromo->enabled);
        self::assertSame('Passenden Drucker finden', $resolved->printerGuidePromo->headline);
        self::assertSame('uploads/channel-homepage/advisor.webp', $resolved->printerGuidePromo->imagePath);
        self::assertFalse($resolved->configuratorPromo->enabled);
        self::assertNull($resolved->configuratorPromo->imagePath);
    }

    public function testSameLocaleDoesNotLeakBetweenChannels(): void
    {
        $cardnext = new Channel();
        $identible = new Channel();
        $first = $this->content($cardnext, 'de_DE', 'Cardnext Hero');
        $second = $this->content($identible, 'de_DE', 'Identible Hero');

        self::assertSame('Cardnext Hero', $this->resolver($cardnext, 'de_DE', [$first, $second])->resolve()->heroTitle);
        self::assertSame('Identible Hero', $this->resolver($identible, 'de_DE', [$first, $second])->resolve()->heroTitle);
    }

    public function testCustomImagesAreIsolatedByChannelAndLocale(): void
    {
        $identible = new Channel();
        $inplastor = new Channel();
        $identibleGerman = $this->content($identible, 'de_DE', 'Identible');
        $identibleGerman->setHeroImagePath('uploads/channel-homepage/identible.webp');
        $identibleAustrian = $this->content($identible, 'de_AT', 'Identible AT');
        $identibleAustrian->setHeroImagePath('uploads/channel-homepage/identible-at.webp');
        $inplastorAustrian = $this->content($inplastor, 'de_AT', 'Inplastor');
        $inplastorAustrian->setHeroImagePath('uploads/channel-homepage/inplastor.webp');
        $contents = [$identibleGerman, $identibleAustrian, $inplastorAustrian];

        self::assertSame('uploads/channel-homepage/identible.webp', $this->resolver($identible, 'de_DE', $contents)->resolve()->heroImagePath);
        self::assertSame('uploads/channel-homepage/identible-at.webp', $this->resolver($identible, 'de_AT', $contents)->resolve()->heroImagePath);
        self::assertSame('uploads/channel-homepage/inplastor.webp', $this->resolver($inplastor, 'de_AT', $contents)->resolve()->heroImagePath);
    }

    public function testSameChannelSelectsTheCurrentLocale(): void
    {
        $channel = new Channel();
        $german = $this->content($channel, 'de_DE', 'Deutsch');
        $austrian = $this->content($channel, 'de_AT', 'Österreich');

        self::assertSame('Deutsch', $this->resolver($channel, 'de_DE', [$german, $austrian])->resolve()->heroTitle);
        self::assertSame('Österreich', $this->resolver($channel, 'de_AT', [$german, $austrian])->resolve()->heroTitle);
    }

    public function testNoRecordUsesExistingTranslations(): void
    {
        $resolved = $this->resolver(new Channel(), 'de_DE', [])->resolve();
        self::assertSame('fallback:cardnext.storefront.homepage.hero.title', $resolved->heroTitle);
        self::assertSame('Warum Cardnext', $resolved->whyKicker);
        self::assertSame('Cardnext SEO', $resolved->metaTitle);
    }

    public function testCustomSeoOverridesFallbacks(): void
    {
        $channel = new Channel();
        $channel->setBrandName('Inplastor');
        $content = $this->content($channel, 'de_AT', 'Hero');
        $content->setHeroText('Markenspezifischer Hero-Text');
        $content->setMetaTitle('Eigener Titel');
        $content->setMetaDescription('Eigene Beschreibung');

        $resolved = $this->resolver($channel, 'de_AT', [$content])->resolve();

        self::assertSame('Eigener Titel', $resolved->metaTitle);
        self::assertSame('Eigene Beschreibung', $resolved->metaDescription);
    }

    private function content(Channel $channel, string $locale, string $title): ChannelHomepageContent
    {
        $content = new ChannelHomepageContent();
        $content->setChannel($channel);
        $content->setLocaleCode($locale);
        $content->setHeroTitle($title);

        return $content;
    }

    /** @param list<ChannelHomepageContent> $contents */
    private function resolver(Channel $channel, string $locale, array $contents): ChannelHomepageContentResolver
    {
        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);
        $localeContext = $this->createStub(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn($locale);
        $repository = new class($contents) extends ChannelHomepageContentRepository {
            /** @param list<ChannelHomepageContent> $contents */
            public function __construct(private readonly array $contents)
            {
            }

            public function findOneForChannelAndLocale(ChannelInterface $channel, string $localeCode): ?ChannelHomepageContent
            {
                foreach ($this->contents as $content) {
                    if ($content->getChannel() === $channel && $content->getLocaleCode() === $localeCode) {
                        return $content;
                    }
                }

                return null;
            }
        };
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = []): string => match ($id) {
            'cardnext.storefront.homepage.why.kicker' => 'Warum ' . ($parameters['%brand%'] ?? 'Cardnext'),
            'cardnext.storefront.homepage.meta_title' => ($parameters['%brand%'] ?? 'Cardnext') . ' SEO',
            default => 'fallback:' . $id,
        });

        return new ChannelHomepageContentResolver($channelContext, $localeContext, $repository, $translator);
    }
}
