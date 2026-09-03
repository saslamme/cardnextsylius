<?php
declare(strict_types=1);
namespace App\Cms;
use App\Entity\Cms\CmsPage; use App\Repository\Cms\CmsPageRepository; use Sylius\Component\Channel\Context\ChannelContextInterface; use Sylius\Component\Locale\Context\LocaleContextInterface;
final readonly class CmsPageResolver { public function __construct(private CmsPageRepository $pages,private CmsPagePublicationChecker $publication,private ChannelContextInterface $channels,private LocaleContextInterface $locales){} public function resolve(string $path):?CmsPage { $path=CmsSlug::normalize($path); if(!CmsSlug::isSafe($path))return null; $channel=$this->channels->getChannel(); $locale=$this->locales->getLocaleCode(); $page=$this->pages->findBySlug($path,$channel,$locale); return $page&&$this->publication->isVisible($page,$channel,$locale)?$page:null; } }
