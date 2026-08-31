<?php

declare(strict_types=1);

namespace App\EventListener;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'sylius.menu.admin.main')]
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $catalog = $menu->getChild('catalog');
        if ($catalog !== null) {
            if ($catalog->getChild('cardnext_configurators') === null) {
                $catalog->addChild('cardnext_configurators', [
                    'route' => 'cardnext_admin_configurator_index',
                    'extras' => ['routes' => [['route' => 'cardnext_admin_configurator_*']]],
                ])->setLabel('cardnext.configurator.title')->setLabelAttribute('icon', 'tabler:adjustments-horizontal');
            }
            if ($catalog->getChild('cardnext_manufacturers') === null) {
                $catalog
                    ->addChild('cardnext_manufacturers', [
                        'route' => 'cardnext_admin_manufacturer_index',
                        'extras' => [
                            'routes' => [
                                ['route' => 'cardnext_admin_manufacturer_create'],
                                ['route' => 'cardnext_admin_manufacturer_update'],
                            ],
                        ],
                    ])
                    ->setLabel('Hersteller')
                    ->setLabelAttribute('icon', 'tabler:building-factory')
                ;
            }

            if ($catalog->getChild('cardnext_product_import') === null) {
                $catalog
                    ->addChild('cardnext_product_import', [
                        'route' => 'cardnext_admin_product_import_index',
                        'extras' => [
                            'routes' => [
                                ['route' => 'cardnext_admin_product_import_preview'],
                                ['route' => 'cardnext_admin_product_import_run'],
                            ],
                        ],
                    ])
                    ->setLabel('Produktimport')
                    ->setLabelAttribute('icon', 'tabler:file-import')
                ;
            }

            if ($catalog->getChild('cardnext_device_models') === null) {
                $catalog->addChild('cardnext_device_models', [
                    'route' => 'cardnext_admin_device_model_index',
                    'extras' => ['routes' => [['route' => 'cardnext_admin_device_model_create'], ['route' => 'cardnext_admin_device_model_update']]],
                ])->setLabel('Gerätemodelle')->setLabelAttribute('icon', 'tabler:devices');
            }
        }

        $customers = $menu->getChild('customers');
        if ($customers !== null && $customers->getChild('cardnext_b2b_customers') === null) {
            $customers
                ->addChild('cardnext_b2b_customers', [
                    'route' => 'cardnext_admin_b2b_customer_index',
                    'extras' => [
                        'routes' => [
                            ['route' => 'cardnext_admin_b2b_customer_create'],
                            ['route' => 'cardnext_admin_b2b_customer_update'],
                            ['route' => 'cardnext_admin_b2b_customer_delete'],
                            ['route' => 'cardnext_admin_b2b_customer_import'],
                        ],
                    ],
                ])
                ->setLabel('B2B-Kunden')
                ->setLabelAttribute('icon', 'tabler:building-store')
            ;
        }
        if ($customers !== null && $customers->getChild('cardnext_maintenance_contracts') === null) {
            $customers->addChild('cardnext_maintenance_contracts', ['route' => 'cardnext_admin_maintenance_contract_index', 'extras' => ['routes' => [['route' => 'cardnext_admin_maintenance_contract_*']]]])->setLabel('cardnext.maintenance_contract.admin.menu')->setLabelAttribute('icon', 'tabler:shield-check');
        }

        if ($menu->getChild('cardnext_quotes') === null) {
            $menu->addChild('cardnext_quotes', [
                'route' => 'cardnext_admin_quote_index',
                'extras' => ['routes' => [['route' => 'cardnext_admin_quote_*']]],
            ])->setLabel('cardnext.quote.admin.menu')->setLabelAttribute('icon', 'tabler:file-description');
        }

        $configuration = $menu->getChild('configuration');
        if ($configuration !== null && $configuration->getChild('cardnext_markets') === null) {
            $configuration
                ->addChild('cardnext_markets', [
                    'route' => 'cardnext_admin_market_overview',
                ])
                ->setLabel('Cardnext Märkte')
                ->setLabelAttribute('icon', 'tabler:world')
            ;
        }
    }
}
