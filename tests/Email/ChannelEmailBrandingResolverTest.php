<?php

declare(strict_types=1);

namespace App\Tests\Email;

use App\Branding\ChannelBrandingResolver;
use App\Email\ChannelEmailBrandingResolver;
use App\Entity\Channel\Channel;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\Validator\Validation;

final class ChannelEmailBrandingResolverTest extends TestCase
{
    public function testResolvesExplicitChannelWithoutRequestContext(): void
    {
        $channel = $this->channel('IDENTIBLE_DE', 'Identible', 'identible.cardnext.de', 'brands/identible.svg');
        $channel->setContactEmail('kontakt@identible.de');
        $branding = $this->resolver()->resolve($channel);

        self::assertSame('Identible', $branding->brandName);
        self::assertSame('Identible', $branding->senderName);
        self::assertSame('info@cardnext.de', $branding->senderAddress);
        self::assertSame('kontakt@identible.de', $branding->replyToAddress);
        self::assertSame('https://identible.cardnext.de/brands/identible.svg', $branding->logoUrl);
    }

    public function testResolvesAllBrandsAndCustomHeaders(): void
    {
        foreach ([['CARDNEXT_DE', null, 'Cardnext'], ['IDENTIBLE_DE', 'Identible', 'Identible'], ['INPLASTOR_AT', 'inplastor', 'inplastor']] as [$code, $configured, $expected]) {
            self::assertSame($expected, $this->resolver()->resolve($this->channel($code, $configured, strtolower($code) . '.example', null))->brandName);
        }

        $channel = $this->channel('IDENTIBLE_DE', 'Identible', 'identible.example', null);
        $channel->setEmailSenderName('Identible Shop');
        $channel->setEmailSenderAddress('mail@identible.de');
        $channel->setEmailReplyToAddress('antwort@identible.de');
        $branding = $this->resolver()->resolve($channel);
        self::assertSame(['Identible Shop', 'mail@identible.de', 'antwort@identible.de'], [$branding->senderName, $branding->senderAddress, $branding->replyToAddress]);
    }

    public function testRejectsInvalidAndInjectedAddresses(): void
    {
        $channel = new Channel();
        $channel->setEmailSenderAddress("valid@example.com\nBcc: victim@example.com");
        $channel->setEmailReplyToAddress('not-an-address');
        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($channel);
        self::assertGreaterThanOrEqual(2, count($violations));
    }

    private function resolver(): ChannelEmailBrandingResolver
    {
        $context = $this->createStub(ChannelContextInterface::class);
        return new ChannelEmailBrandingResolver(new ChannelBrandingResolver($context), 'info@cardnext.de');
    }

    private function channel(string $code, ?string $brand, string $host, ?string $logo): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setBrandName($brand);
        $channel->setHostname($host);
        $channel->setLogoPath($logo);
        return $channel;
    }
}
