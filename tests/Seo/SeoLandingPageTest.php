<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use App\Entity\Channel\Channel;
use App\Entity\Locale\Locale;
use App\Entity\Seo\SeoLandingPage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class SeoLandingPageTest extends TestCase
{
    public function testRobotsAndCanonicalConfiguration(): void { $page = new SeoLandingPage(); self::assertSame('index,follow', $page->getRobots()); $page->setRobotsIndex(false); $page->setRobotsFollow(false); self::assertSame('noindex,nofollow', $page->getRobots()); $page->setCanonicalUrl('https://example.com/canonical'); self::assertSame('https://example.com/canonical', $page->getCanonicalUrl()); }

    public function testLocaleMustBelongToSelectedChannel(): void
    {
        $channel = new Channel();
        $locale = new Locale();
        $locale->setCode('de_DE');
        $channel->addLocale($locale);
        $page = new SeoLandingPage();
        $page->setChannel($channel);
        $page->setLocale('en_US');

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $messages = array_map(static fn ($violation): string => (string) $violation->getMessage(), iterator_to_array($validator->validate($page)));
        self::assertContains('Die gewählte Sprache ist diesem Verkaufskanal nicht zugeordnet.', $messages);
        $page->setLocale('de_DE');
        $messages = array_map(static fn ($violation): string => (string) $violation->getMessage(), iterator_to_array($validator->validate($page)));
        self::assertNotContains('Die gewählte Sprache ist diesem Verkaufskanal nicht zugeordnet.', $messages);
    }
}
