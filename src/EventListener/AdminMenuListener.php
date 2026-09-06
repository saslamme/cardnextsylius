<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Admin\Menu\CardnextAdminMenuBuilder;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.admin.main')]
final class AdminMenuListener
{
    public function __construct(private readonly CardnextAdminMenuBuilder $menuBuilder)
    {
    }

    public function __invoke(MenuBuilderEvent $event): void
    {
        $this->menuBuilder->build($event->getMenu());
    }
}
