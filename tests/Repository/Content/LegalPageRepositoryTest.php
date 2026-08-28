<?php

declare(strict_types=1);

namespace App\Tests\Repository\Content;

use App\Repository\Content\LegalPageRepository;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Model\ChannelInterface;

final class LegalPageRepositoryTest extends TestCase
{
    public function testChannelAwareFinderHasAnExplicitContract(): void
    {
        $method = new \ReflectionMethod(LegalPageRepository::class, 'findOneByTypeAndChannel');
        $parameters = $method->getParameters();

        self::assertSame('type', $parameters[0]->getName());
        self::assertSame(ChannelInterface::class, (string) $parameters[1]->getType());
        self::assertSame('localeCode', $parameters[2]->getName());
        self::assertTrue($method->getReturnType()?->allowsNull());
    }
}
