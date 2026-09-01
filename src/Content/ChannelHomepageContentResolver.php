<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\Channel\Channel;
use App\Repository\Content\ChannelHomepageContentRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ChannelHomepageContentResolver
{
    /** @var array<string, ResolvedHomepageContent> */
    private array $resolved = [];

    public function __construct(private readonly ChannelContextInterface $channelContext, private readonly LocaleContextInterface $localeContext, private readonly ChannelHomepageContentRepository $repository, private readonly TranslatorInterface $translator)
    {
    }

    public function resolve(): ResolvedHomepageContent
    {
        $channel = $this->channelContext->getChannel();
        $locale = $this->localeContext->getLocaleCode();
        $key = (string) ($channel->getId() ?? spl_object_id($channel)) . ':' . $locale;
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }
        $content = $this->repository->findOneForChannelAndLocale($channel, $locale);
        $brand = $channel instanceof Channel ? ($channel->getBrandName() ?? 'Cardnext') : 'Cardnext';
        $fallback = fn (string $key, array $parameters = []): string => $this->translator->trans($key, $parameters, null, $locale);
        $value = static fn (?string $custom, string $default): string => $custom !== null && trim($custom) !== '' ? $custom : $default;

        return $this->resolved[$key] = new ResolvedHomepageContent(
            $value($content?->getHeroKicker(), $fallback('cardnext.storefront.homepage.hero.kicker')),
            $value($content?->getHeroTitle(), $fallback('cardnext.storefront.homepage.hero.title')),
            $value($content?->getHeroText(), $fallback('cardnext.storefront.homepage.hero.text')),
            $value($content?->getIntroKicker(), $fallback('cardnext.storefront.homepage.service.kicker')),
            $value($content?->getIntroTitle(), $fallback('cardnext.storefront.homepage.service.title')),
            $value($content?->getIntroText(), $fallback('cardnext.storefront.homepage.service.text')),
            $value($content?->getWhyKicker(), $fallback('cardnext.storefront.homepage.why.kicker', ['%brand%' => $brand])),
            $value($content?->getWhyTitle(), $fallback('cardnext.storefront.homepage.why.title')),
            $value($content?->getWhyText(), $fallback('cardnext.storefront.homepage.why.text')),
            $content?->getCtaKicker() ?? '',
            $value($content?->getCtaTitle(), $fallback('cardnext.storefront.homepage.cta.title')),
            $value($content?->getCtaText(), $fallback('cardnext.storefront.homepage.cta.text')),
            $value($content?->getFooterText(), $fallback('cardnext.storefront.footer.description')),
            $value($content?->getHeroImagePath(), 'cardnext/homepage/hero-card-printer.webp'),
            $value($content?->getIntroImagePath(), 'cardnext/homepage/service-consultation.webp'),
            $value($content?->getCtaImagePath(), 'cardnext/homepage/support-advisor.webp'),
        );
    }
}
