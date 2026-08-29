<?php
declare(strict_types=1);
namespace App\Tests\Quote;
use App\Entity\Quote\Quote;
use App\Enum\Quote\QuoteStatus;
use App\Service\Quote\QuotePublicAccessTokenManager;
use PHPUnit\Framework\TestCase;
final class QuotePublicAccessTokenManagerTest extends TestCase
{
 public function testOnlyHashIsStoredAndTokensAreRotated():void
 {
  $q=new Quote();$manager=new QuotePublicAccessTokenManager();$first=$manager->issue($q);
  self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/',$first);self::assertNotSame($first,$q->getAccessTokenHash());self::assertSame(hash('sha256',$first),$q->getAccessTokenHash());self::assertTrue($manager->isValid($q,$first));self::assertFalse($manager->isValid($q,str_repeat('0',64)));
  $second=$manager->issue($q);self::assertNotSame($first,$second);self::assertFalse($manager->isValid($q,$first));self::assertTrue($manager->isValid($q,$second));
 }
 public function testExplicitDomainTransitionsAndDecisions():void
 {
  $q=new Quote();$q->transitionTo(QuoteStatus::Ready);$q->recordSent(new \DateTimeImmutable());$q->accept('Max Mustermann',new \DateTimeImmutable());self::assertSame(QuoteStatus::Accepted,$q->getStatus());self::assertSame('Max Mustermann',$q->getAcceptedByName());$this->expectException(\DomainException::class);$q->transitionTo(QuoteStatus::Rejected);
 }
}
