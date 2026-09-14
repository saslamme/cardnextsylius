<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Cms\CmsSlug;
use App\Entity\Seo\SeoLandingPage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
final class SeoLandingPagePathRedirectSubscriber
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $em = $event->getObjectManager(); $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $page) {
            if (!$page instanceof SeoLandingPage || !$page->isEnabled()) continue;
            $change = $uow->getEntityChangeSet($page)['path'] ?? null;
            if (!is_array($change) || $page->getChannel() === null) continue;
            $old = CmsSlug::normalize((string) $change[0]); $new = CmsSlug::normalize((string) $change[1]);
            if ($old === '' || $old === $new) continue;
            $em->getConnection()->executeStatement('INSERT INTO cardnext_cms_redirect (channel_id,target_page_id,locale,source_path,target_path,status_code,created_at) VALUES (:channel,NULL,:locale,:source,:target,301,NOW()) ON DUPLICATE KEY UPDATE target_path=VALUES(target_path), status_code=301', ['channel' => $page->getChannel()->getId(), 'locale' => $page->getLocale(), 'source' => $old, 'target' => $new]);
        }
    }
}
