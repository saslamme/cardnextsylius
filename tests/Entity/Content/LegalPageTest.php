<?php

declare(strict_types=1);

namespace App\Tests\Entity\Content;

use App\Entity\Channel\Channel;
use App\Entity\Content\LegalPage;
use PHPUnit\Framework\TestCase;

final class LegalPageTest extends TestCase
{
    public function testItCanBeAssignedToMultipleChannels(): void
    {
        $germany = new Channel();
        $germany->setCode('CARDNEXT_DE');
        $austria = new Channel();
        $austria->setCode('CARDNEXT_AT');
        $page = new LegalPage();

        $page->addChannel($germany);
        $page->addChannel($austria);
        $page->addChannel($germany);

        self::assertCount(2, $page->getChannels());
        self::assertTrue($page->getChannels()->contains($germany));
        self::assertTrue($page->getChannels()->contains($austria));

        $page->removeChannel($austria);
        self::assertFalse($page->getChannels()->contains($austria));
    }
}
