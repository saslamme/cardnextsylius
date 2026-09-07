<?php
declare(strict_types=1);
namespace App\Cms;
use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsPage;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
final readonly class CmsHomepageResolver
{
 public function __construct(private ChannelContextInterface $channels,private LocaleContextInterface $locales,private CmsPagePublicationChecker $publication){}
 /** @return array{page:CmsPage,translation:\App\Entity\Cms\CmsPageTranslation,locale:string}|null */
 public function resolve():?array{$channel=$this->channels->getChannel();$locale=$this->locales->getLocaleCode();if(!$channel instanceof Channel||!($page=$channel->getHomepageCmsPage()) instanceof CmsPage)return null;$translation=$page->getTranslation($locale);if($translation===null||!$this->publication->isVisible($page,$channel,$locale))return null;return ['page'=>$page,'translation'=>$translation,'locale'=>$locale];}
}
