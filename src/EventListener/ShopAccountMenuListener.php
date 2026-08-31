<?php

declare(strict_types=1);

namespace App\EventListener;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.shop.account')]
final class ShopAccountMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $item = $menu->addChild('quotes', ['route' => 'cardnext_shop_account_quote_index'])->setLabel('cardnext.account.quotes');
        $item->setLabelAttribute('icon', 'tabler:file-invoice');
        $maintenance = $menu->addChild('maintenance_contracts', ['route' => 'cardnext_shop_account_maintenance_contract_index'])->setLabel('cardnext.account.maintenance_contracts');
        $maintenance->setLabelAttribute('icon', 'tabler:shield-check');
        $menu->reorderChildren(['dashboard', 'order_history', 'quotes', 'maintenance_contracts', 'address_book', 'personal_information', 'change_password']);
    }
}
