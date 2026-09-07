<?php
declare(strict_types=1);
namespace App\Tests\Cms;
use App\Cms\CmsHomepageResolver;
use App\Cms\CmsPagePublicationChecker;
use App\Entity\Channel\Channel;
use App\Entity\Cms\{CmsBlock,CmsLayout,CmsPage,CmsPageTranslation};
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
final class CmsHomepageTest extends TestCase
{
 public function testHomepageIsAssignedPerChannelAndResolvedPerLocale():void
 {
  $de=$this->channel('DE');$at=$this->channel('AT');$dePage=$this->page($de,'Startseite','de_DE');$atPage=$this->page($at,'Startseite AT','de_AT');$de->setHomepageCmsPage($dePage);$at->setHomepageCmsPage($atPage);
  $deResult=$this->resolver($de,'de_DE')->resolve();$atResult=$this->resolver($at,'de_AT')->resolve();self::assertNotNull($deResult);self::assertNotNull($atResult);self::assertSame($dePage,$deResult['page']);self::assertSame('Startseite',$deResult['translation']->getTitle());self::assertSame($atPage,$atResult['page']);self::assertNull($this->resolver($de,'de_AT')->resolve());
 }
 public function testBlocksCanBeFilteredAndSortedForTheHomepage():void
 {
  $channel=$this->channel('DE');$page=$this->page($channel,'Startseite','de_DE');$channel->setHomepageCmsPage($page);foreach([[30,true],[10,false],[20,true]] as [$position,$enabled]){$block=new CmsBlock();$block->setLocale('de_DE');$block->setPosition($position);$block->setEnabled($enabled);$page->addBlock($block);}
  $visible=array_values(array_filter($page->getBlocks()->toArray(),static fn(CmsBlock $block):bool=>$block->isEnabled()&&$block->getLocale()==='de_DE'));usort($visible,static fn(CmsBlock $a,CmsBlock $b):int=>$a->getPosition()<=>$b->getPosition());self::assertSame([20,30],array_map(static fn(CmsBlock $block):int=>$block->getPosition(),$visible));
 }
 private function channel(string $code):Channel{$channel=new Channel();$channel->setCode($code);return $channel;}
 private function page(Channel $channel,string $title,string $locale):CmsPage{$layout=new CmsLayout();$layout->setCode('homepage');$layout->setName('Homepage');$layout->setEnabled(true);$page=new CmsPage();$page->setCode(strtolower((string)$channel->getCode()).'_home');$page->setLayout($layout);$page->setStatus(CmsPage::STATUS_PUBLISHED);$page->addChannel($channel);$translation=new CmsPageTranslation();$translation->setLocale($locale);$translation->setTitle($title);$translation->setSlug('internal-home');$page->addTranslation($translation);return $page;}
 private function resolver(Channel $channel,string $locale):CmsHomepageResolver{$channels=$this->createStub(ChannelContextInterface::class);$channels->method('getChannel')->willReturn($channel);$locales=$this->createStub(LocaleContextInterface::class);$locales->method('getLocaleCode')->willReturn($locale);return new CmsHomepageResolver($channels,$locales,new CmsPagePublicationChecker());}
}
