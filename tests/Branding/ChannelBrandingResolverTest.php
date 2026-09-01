<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use App\Branding\ChannelBrandingResolver;
use App\Entity\Channel\Channel;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\Validator\Validation;

final class ChannelBrandingResolverTest extends TestCase
{
    public function testItProvidesCardnextFallbackWithoutAdditionalLookups(): void
    {
        $channel = new Channel();
        $context = $this->createMock(ChannelContextInterface::class);
        $context->expects(self::once())->method('getChannel')->willReturn($channel);

        $branding = (new ChannelBrandingResolver($context))->resolve();

        self::assertSame('cardnext', $branding->themeKey);
        self::assertSame('Cardnext', $branding->brandName);
        self::assertSame('cardnext/cardnext.svg', $branding->logoPath);
        self::assertSame([], $branding->cssVariables);
    }

    public function testItResolvesCustomBranding(): void
    {
        $channel = new Channel();
        $channel->setThemeKey('identible');
        $channel->setBrandName('Identible');
        $channel->setLogoPath('uploads/channel-branding/logo.webp');
        $channel->setPrimaryColor('#123456');
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);

        $branding = (new ChannelBrandingResolver($context))->resolve();

        self::assertSame('identible', $branding->themeKey);
        self::assertSame('Identible', $branding->brandName);
        self::assertSame('#123456', $branding->cssVariables['--cn-primary']);
    }

    public function testItRejectsCssInjectionAsAColor(): void
    {
        $channel = new Channel();
        $channel->setPrimaryColor('red; background:url(x)');

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($channel);

        self::assertGreaterThan(0, $violations->count());
    }
}
