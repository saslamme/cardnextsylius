<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AdminHomepageContentMenuSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['sylius.menu.admin.main' => 'add'];
    }

    public function add(MenuBuilderEvent $event): void
    {
        $content = $event->getMenu()->getChild('cardnext_content') ?? $event->getMenu()->addChild('cardnext_content')->setLabel('Inhalte')->setExtra('always_open', true);
        if ($content->getChild('cardnext_homepage_content') === null) {
            $content->addChild('cardnext_homepage_content', ['route' => 'cardnext_admin_homepage_content_index', 'extras' => ['routes' => [['route' => 'cardnext_admin_homepage_content_create'], ['route' => 'cardnext_admin_homepage_content_edit']]]])->setLabel('Homepage-Inhalte')->setLabelAttribute('icon', 'tabler:home-edit');
        }
    }
}
