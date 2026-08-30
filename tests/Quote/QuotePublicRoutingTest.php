<?php
declare(strict_types=1);
namespace App\Tests\Quote;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
final class QuotePublicRoutingTest extends KernelTestCase
{
 public function testLegacyPublicQuoteRouteNoLongerExists():void
 {
  self::bootKernel();$router=self::getContainer()->get('router');
  $this->expectException(ResourceNotFoundException::class);
  $router->match('/de_DE/angebot/AG-2026-00003/v1/'.str_repeat('a',64));
 }
 public function testAccountRoutesExist():void
 {
  self::bootKernel();$router=self::getContainer()->get('router');
  self::assertSame('cardnext_shop_account_quote_show',$router->match('/de_DE/account/angebote/AG-2026-00003/v1')['_route']);
 }
}
