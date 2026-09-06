<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Admin\Menu\CardnextAdminMenuBuilder;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\TestCase;

final class CardnextAdminMenuTest extends TestCase
{
    private ItemInterface $menu;

    protected function setUp(): void
    {
        $this->menu = (new MenuFactory())->createItem('root');
        foreach (['sales', 'catalog', 'customers', 'configuration'] as $parent) {
            $this->menu->addChild($parent)->addChild(sprintf('sylius_%s_item', $parent));
        }

        (new CardnextAdminMenuBuilder())->build($this->menu);
    }

    public function testItCreatesTheContentMenuInTheExpectedOrder(): void
    {
        $content = $this->requireChild($this->menu, 'cardnext_content');

        self::assertSame('Inhalte', $content->getLabel());
        self::assertSame('tabler:file-text', $content->getLabelAttribute('icon'));
        self::assertSame([
            'cardnext_homepage_content',
            'cardnext_cms_pages',
            'cardnext_cms_menus',
            'cardnext_cms_downloads',
            'cardnext_legal_pages',
            'cardnext_cms_layouts',
        ], array_keys($content->getChildren()));

        $this->assertLabels($content, [
            'cardnext_homepage_content' => 'Homepage',
            'cardnext_cms_pages' => 'CMS-Seiten',
            'cardnext_cms_menus' => 'Navigation',
            'cardnext_cms_downloads' => 'Downloads',
            'cardnext_legal_pages' => 'Rechtstexte',
            'cardnext_cms_layouts' => 'Layouts',
        ]);
    }

    public function testItCreatesTheToolsMenuAndKeepsImportsOutOfOperationalSections(): void
    {
        $tools = $this->requireChild($this->menu, 'cardnext_tools');

        self::assertSame('Werkzeuge', $tools->getLabel());
        self::assertSame('tabler:tool', $tools->getLabelAttribute('icon'));
        $this->assertLabels($tools, [
            'cardnext_product_import' => 'Produktimport',
            'cardnext_customer_import' => 'Kundenimport',
            'cardnext_channel_pricing_copy' => 'Preise übertragen',
        ]);

        $catalog = $this->requireChild($this->menu, 'catalog');
        self::assertNull($catalog->getChild('cardnext_product_import'));
        self::assertNull($catalog->getChild('cardnext_channel_pricing_copy'));

        $customers = $this->requireChild($this->menu, 'customers');
        self::assertNull($customers->getChild('cardnext_customer_import'));
    }

    public function testItAddsMasterDataAfterExistingSyliusItems(): void
    {
        $catalog = $this->requireChild($this->menu, 'catalog');
        self::assertSame([
            'sylius_catalog_item',
            'cardnext_manufacturers',
            'cardnext_device_models',
            'cardnext_configurators',
        ], array_keys($catalog->getChildren()));
        $this->assertLabels($catalog, [
            'cardnext_manufacturers' => 'Hersteller',
            'cardnext_device_models' => 'Gerätemodelle',
            'cardnext_configurators' => 'Konfiguratoren',
        ]);

        $customers = $this->requireChild($this->menu, 'customers');
        $this->assertLabels($customers, [
            'cardnext_b2b_customers' => 'B2B-Kunden',
            'cardnext_maintenance_contracts' => 'Wartungsverträge',
        ]);

        self::assertSame('Märkte', $this->requireChild($this->requireChild($this->menu, 'configuration'), 'cardnext_markets')->getLabel());
    }

    public function testItIntegratesQuotesIntoTheExistingSalesMenu(): void
    {
        self::assertNull($this->menu->getChild('cardnext_quotes'));

        $quotes = $this->requireChild($this->requireChild($this->menu, 'sales'), 'cardnext_quotes');
        self::assertSame('Angebote', $quotes->getLabel());
        self::assertSame([['route' => 'cardnext_admin_quote_*']], $quotes->getExtra('routes'));
    }

    public function testItAddsEveryCardnextEntryOnlyOnceAndIsIdempotent(): void
    {
        (new CardnextAdminMenuBuilder())->build($this->menu);

        $names = $this->collectNames($this->menu);
        foreach (array_unique($names) as $name) {
            if (str_starts_with($name, 'cardnext_')) {
                self::assertSame(1, array_count_values($names)[$name], sprintf('%s was registered more than once.', $name));
            }
        }
    }

    public function testEntriesExposeMetadataForTheirSecondaryRoutes(): void
    {
        $expectations = [
            ['catalog', 'cardnext_manufacturers', 'cardnext_admin_manufacturer_create'],
            ['catalog', 'cardnext_device_models', 'cardnext_admin_device_model_update'],
            ['customers', 'cardnext_b2b_customers', 'cardnext_admin_b2b_customer_import'],
            ['cardnext_content', 'cardnext_cms_pages', 'cardnext_admin_cms_block_*'],
            ['cardnext_content', 'cardnext_legal_pages', 'cardnext_admin_legal_page_create'],
            ['cardnext_tools', 'cardnext_product_import', 'cardnext_admin_product_import_run'],
            ['cardnext_tools', 'cardnext_customer_import', 'cardnext_admin_customer_import_*'],
            ['cardnext_tools', 'cardnext_channel_pricing_copy', 'cardnext_admin_channel_pricing_copy*'],
        ];

        foreach ($expectations as [$parentName, $childName, $route]) {
            $child = $this->requireChild($this->requireChild($this->menu, $parentName), $childName);
            $activeRoutes = $child->getExtra('routes');
            self::assertIsArray($activeRoutes);
            self::assertContains(['route' => $route], $activeRoutes);
        }
    }

    public function testItGracefullySkipsMissingSyliusParents(): void
    {
        $menu = (new MenuFactory())->createItem('root');

        (new CardnextAdminMenuBuilder())->build($menu);

        self::assertNull($menu->getChild('cardnext_quotes'));
        self::assertNotNull($menu->getChild('cardnext_content'));
        self::assertNotNull($menu->getChild('cardnext_tools'));
    }

    /** @param array<string, string> $labels */
    private function assertLabels(ItemInterface $parent, array $labels): void
    {
        foreach ($labels as $name => $label) {
            self::assertSame($label, $this->requireChild($parent, $name)->getLabel());
        }
    }

    private function requireChild(ItemInterface $parent, string $name): ItemInterface
    {
        $child = $parent->getChild($name);
        self::assertNotNull($child, sprintf('Expected menu item "%s".', $name));

        return $child;
    }

    /** @return list<string> */
    private function collectNames(ItemInterface $item): array
    {
        $names = [$item->getName()];
        foreach ($item->getChildren() as $child) {
            array_push($names, ...$this->collectNames($child));
        }

        return $names;
    }
}
