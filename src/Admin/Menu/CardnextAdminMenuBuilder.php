<?php

declare(strict_types=1);

namespace App\Admin\Menu;

use Knp\Menu\ItemInterface;

final class CardnextAdminMenuBuilder
{
    public function build(ItemInterface $menu): void
    {
        $this->addSalesItems($menu);
        $this->addCatalogItems($menu);
        $this->addCustomerItems($menu);
        $this->addContentMenu($menu);
        $this->addConfigurationItems($menu);
        $this->addToolsMenu($menu);
    }

    private function addSalesItems(ItemInterface $menu): void
    {
        $sales = $this->findSuitableParent($menu, ['sales', 'orders']);
        if ($sales === null) {
            return;
        }

        $this->addChildIfMissing($sales, 'cardnext_quotes', 'Angebote', 'tabler:file-description', 'cardnext_admin_quote_index', [
            'cardnext_admin_quote_*',
        ]);
    }

    private function addCatalogItems(ItemInterface $menu): void
    {
        $catalog = $menu->getChild('catalog');
        if ($catalog === null) {
            return;
        }

        $this->addChildIfMissing($catalog, 'cardnext_manufacturers', 'Hersteller', 'tabler:building-factory', 'cardnext_admin_manufacturer_index', [
            'cardnext_admin_manufacturer_create',
            'cardnext_admin_manufacturer_update',
            'cardnext_admin_manufacturer_delete',
        ]);
        $this->addChildIfMissing($catalog, 'cardnext_device_models', 'Gerätemodelle', 'tabler:devices', 'cardnext_admin_device_model_index', [
            'cardnext_admin_device_model_create',
            'cardnext_admin_device_model_update',
            'cardnext_admin_device_model_delete',
        ]);
        $this->addChildIfMissing($catalog, 'cardnext_configurators', 'Konfiguratoren', 'tabler:adjustments-horizontal', 'cardnext_admin_configurator_index', [
            'cardnext_admin_configurator_*',
        ]);
    }

    private function addCustomerItems(ItemInterface $menu): void
    {
        $customers = $menu->getChild('customers');
        if ($customers === null) {
            return;
        }

        $this->addChildIfMissing($customers, 'cardnext_b2b_customers', 'B2B-Kunden', 'tabler:building-store', 'cardnext_admin_b2b_customer_index', [
            'cardnext_admin_b2b_customer_create',
            'cardnext_admin_b2b_customer_update',
            'cardnext_admin_b2b_customer_delete',
            'cardnext_admin_b2b_customer_import',
        ]);
        $this->addChildIfMissing($customers, 'cardnext_maintenance_contracts', 'Wartungsverträge', 'tabler:shield-check', 'cardnext_admin_maintenance_contract_index', [
            'cardnext_admin_maintenance_contract_*',
        ]);
    }

    private function addContentMenu(ItemInterface $menu): void
    {
        $content = $menu->getChild('cardnext_content');
        if ($content === null) {
            $content = $menu
                ->addChild('cardnext_content')
                ->setLabel('Inhalte')
                ->setLabelAttribute('icon', 'tabler:file-text')
                ->setExtra('always_open', true)
            ;
        }

        $this->addChildIfMissing($content, 'cardnext_cms_pages', 'CMS-Seiten', 'tabler:file', 'cardnext_admin_cms_pages', [
            'cardnext_admin_cms_page_*',
            'cardnext_admin_cms_block_*',
        ]);
        $this->addChildIfMissing($content, 'cardnext_cms_menus', 'Navigation', 'tabler:menu-2', 'cardnext_admin_cms_menus', [
            'cardnext_admin_cms_menu_*',
        ]);
        $this->addChildIfMissing($content, 'cardnext_cms_downloads', 'Downloads', 'tabler:download', 'cardnext_admin_cms_downloads', [
            'cardnext_admin_cms_download_*',
        ]);
        $this->addChildIfMissing($content, 'cardnext_legal_pages', 'Rechtstexte', 'tabler:scale', 'cardnext_admin_legal_page_index', [
            'cardnext_admin_legal_page_create',
            'cardnext_admin_legal_page_edit',
        ]);
        $this->addChildIfMissing($content, 'cardnext_cms_layouts', 'Layouts', 'tabler:layout', 'cardnext_admin_cms_layouts', [
            'cardnext_admin_cms_layout_*',
        ]);
    }

    private function addConfigurationItems(ItemInterface $menu): void
    {
        $configuration = $menu->getChild('configuration');
        if ($configuration === null) {
            return;
        }

        $this->addChildIfMissing($configuration, 'cardnext_markets', 'Märkte', 'tabler:world', 'cardnext_admin_market_overview');
    }

    private function addToolsMenu(ItemInterface $menu): void
    {
        $tools = $menu->getChild('cardnext_tools');
        if ($tools === null) {
            $tools = $menu
                ->addChild('cardnext_tools')
                ->setLabel('Werkzeuge')
                ->setLabelAttribute('icon', 'tabler:tool')
                ->setExtra('always_open', true)
            ;
        }

        $this->addChildIfMissing($tools, 'cardnext_product_import', 'Produktimport', 'tabler:file-import', 'cardnext_admin_product_import_index', [
            'cardnext_admin_product_import_preview',
            'cardnext_admin_product_import_run',
            'cardnext_admin_product_import_template',
        ]);
        $this->addChildIfMissing($tools, 'cardnext_customer_import', 'Kundenimport', 'tabler:users-plus', 'cardnext_admin_customer_import', [
            'cardnext_admin_customer_import_*',
        ]);
        $this->addChildIfMissing($tools, 'cardnext_channel_pricing_copy', 'Preise übertragen', 'tabler:transfer', 'cardnext_admin_channel_pricing_copy', [
            'cardnext_admin_channel_pricing_copy*',
        ]);
    }

    /** @param list<string> $parentNames */
    private function findSuitableParent(ItemInterface $menu, array $parentNames): ?ItemInterface
    {
        foreach ($parentNames as $parentName) {
            $parent = $menu->getChild($parentName);
            if ($parent !== null) {
                return $parent;
            }
        }

        return null;
    }

    /** @param list<string> $activeRoutes */
    private function addChildIfMissing(
        ItemInterface $parent,
        string $name,
        string $label,
        string $icon,
        string $route,
        array $activeRoutes = [],
    ): void {
        if ($parent->getChild($name) !== null) {
            return;
        }

        $options = ['route' => $route];
        if ($activeRoutes !== []) {
            $options['extras'] = [
                'routes' => array_map(static fn (string $activeRoute): array => ['route' => $activeRoute], $activeRoutes),
            ];
        }

        $parent
            ->addChild($name, $options)
            ->setLabel($label)
            ->setLabelAttribute('icon', $icon)
        ;
    }
}
