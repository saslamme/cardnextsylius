<?php
declare(strict_types=1);
namespace App\Cms;
use App\Entity\Cms\CmsPage; use Sylius\Component\Channel\Model\ChannelInterface;
final class CmsPagePublicationChecker { public function isVisible(CmsPage $page,ChannelInterface $channel,string $locale,?\DateTimeImmutable $now=null):bool { $now??=new \DateTimeImmutable(); return $page->getStatus()===CmsPage::STATUS_PUBLISHED&&$page->getLayout()?->isEnabled()===true&&$page->getChannels()->contains($channel)&&$page->getTranslation($locale)!==null&&($page->getPublishAt()===null||$page->getPublishAt()<=$now)&&($page->getUnpublishAt()===null||$page->getUnpublishAt()>$now); } }
