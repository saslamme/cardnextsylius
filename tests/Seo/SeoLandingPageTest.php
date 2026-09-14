<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use App\Entity\Seo\SeoLandingPage;
use PHPUnit\Framework\TestCase;

final class SeoLandingPageTest extends TestCase
{
    public function testRobotsAndCanonicalConfiguration(): void { $page = new SeoLandingPage(); self::assertSame('index,follow', $page->getRobots()); $page->setRobotsIndex(false); $page->setRobotsFollow(false); self::assertSame('noindex,nofollow', $page->getRobots()); $page->setCanonicalUrl('https://example.com/canonical'); self::assertSame('https://example.com/canonical', $page->getCanonicalUrl()); }
}
