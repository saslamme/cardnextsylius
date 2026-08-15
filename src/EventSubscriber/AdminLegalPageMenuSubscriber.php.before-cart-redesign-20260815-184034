<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AdminLegalPageMenuSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['sylius.menu.admin.main' => 'addLegalPages'];
    }

    public function addLegalPages(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $content = $menu->getChild('cardnext_content');

        if ($content === null) {
            $content = $menu
                ->addChild('cardnext_content')
                ->setLabel('Inhalte')
                ->setLabelAttribute('icon', 'tabler:file-text')
                ->setExtra('always_open', true)
            ;
        }

        if ($content->getChild('cardnext_legal_pages') !== null) {
            return;
        }

        $content
            ->addChild('cardnext_legal_pages', [
                'route' => 'cardnext_admin_legal_page_index',
                'extras' => [
                    'routes' => [
                        ['route' => 'cardnext_admin_legal_page_edit'],
                    ],
                ],
            ])
            ->setLabel('Rechtstexte')
            ->setLabelAttribute('icon', 'tabler:scale')
        ;
    }
}
