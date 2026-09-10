<?php

declare(strict_types=1);

namespace App\Tests\Social;

use App\Entity\Channel\Channel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class ChannelSocialUrlValidationTest extends TestCase
{
    public function testInvalidAndNonHttpsSocialUrlsAreRejected(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        foreach (['instagram', 'www.instagram.com/test', 'http://example.com/profile'] as $url) {
            $channel = new Channel();
            $channel->setInstagramUrl($url);
            self::assertGreaterThan(0, $validator->validate($channel)->count());
        }
    }

    public function testHttpsSocialUrlIsAccepted(): void
    {
        $channel = new Channel();
        $channel->setInstagramUrl('https://www.instagram.com/cardnext/');
        self::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($channel));
    }
}
