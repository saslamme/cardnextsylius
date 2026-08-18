<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use PHPUnit\Framework\TestCase;

final class StorefrontRegressionTest extends TestCase
{
    public function testPublicSlugControllerPassesResolvedTaxonToCategoryView(): void
    {
        $controller = $this->readProjectFile('src/Controller/Shop/PublicSlugController.php');

        self::assertStringContainsString("attributes->set('cardnext_taxon', \$taxon)", $controller);
        self::assertLessThan(
            strpos($controller, 'indexAction($request)'),
            strpos($controller, "attributes->set('cardnext_taxon', \$taxon)"),
        );
    }

    public function testCategoryTemplateNeverPassesNullToConfiguratorComponent(): void
    {
        $template = $this->readProjectFile('templates/bundles/SyliusShopBundle/product/index.html.twig');

        self::assertStringContainsString("{% set cardnext_taxon = app.request.attributes.get('cardnext_taxon') %}", $template);
        self::assertStringContainsString('{% if cardnext_taxon %}', $template);
        self::assertStringContainsString("component('cardnext:taxon:configurators', {taxon: cardnext_taxon})", $template);
        self::assertStringNotContainsString('{taxon: taxon}', $template);
    }

    public function testCategoryKeepsProductsAndOptionallyAddsConfigurators(): void
    {
        $categoryTemplate = $this->readProjectFile('templates/bundles/SyliusShopBundle/product/index.html.twig');
        $configuratorTemplate = $this->readProjectFile('templates/shop/category/configurator_list.html.twig');

        self::assertStringContainsString("component('cardnext:product:card', {product: product})", $categoryTemplate);
        self::assertStringContainsString("component('cardnext:taxon:configurators'", $categoryTemplate);
        self::assertStringContainsString('{% if configurators|length %}', $configuratorTemplate);
    }

    public function testProductHookUsesStandardSyliusSummary(): void
    {
        $hooks = $this->readProjectFile('config/packages/cardnext_product_layout.yaml');

        self::assertStringContainsString("template: '@SyliusShop/product/show/content/info/summary.html.twig'", $hooks);
        self::assertStringNotContainsString('configurable_summary', $hooks);
    }

    public function testProductStorefrontHasNoLegacyConfiguratorReferences(): void
    {
        $paths = [
            'config/packages/cardnext_product_layout.yaml',
            'templates/bundles/SyliusShopBundle/product',
            'templates/shop/product',
        ];
        $legacyTerms = ['configurable_summary', 'isConfigurable', 'productKind', 'ProductKind', 'configuratorPath'];

        foreach ($this->filesIn($paths) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);

            foreach ($legacyTerms as $legacyTerm) {
                self::assertStringNotContainsString($legacyTerm, $contents, sprintf('%s contains legacy product configurator term %s.', $file, $legacyTerm));
            }
        }
    }

    public function testDependencyCleanupRunsOnlyAfterAllRulesHaveBeenApplied(): void
    {
        $javascript = $this->readProjectFile('assets/shop/configurator.js');
        $applyDependenciesStart = strpos($javascript, 'const applyDependencies = () =>');
        $applyRuleStart = strpos($javascript, 'const applyRule = (rule, active) =>');
        self::assertIsInt($applyDependenciesStart);
        self::assertIsInt($applyRuleStart);

        $applyDependencies = substr($javascript, $applyDependenciesStart, $applyRuleStart - $applyDependenciesStart);
        $matchingRules = strpos($applyDependencies, 'dependencies.filter(dependencyMatches)');
        $cleanup = strpos($applyDependencies, 'if (!controlIsEffective(control)) clearControl(control);');
        self::assertIsInt($matchingRules);
        self::assertIsInt($cleanup);
        self::assertGreaterThan($matchingRules, $cleanup);

        $applyRuleEnd = strpos($javascript, "\n    };", $applyRuleStart);
        self::assertIsInt($applyRuleEnd);
        $applyRule = substr($javascript, $applyRuleStart, $applyRuleEnd - $applyRuleStart);
        self::assertStringNotContainsString('clearControl', $applyRule);
    }

    public function testIncompleteConfigurationsAreInvalidatedBeforePriceRequests(): void
    {
        $javascript = $this->readProjectFile('assets/shop/configurator.js');

        self::assertStringContainsString('const incompleteConfigurationErrors = () =>', $javascript);
        self::assertStringContainsString('const invalidateResult = () =>', $javascript);
        self::assertStringContainsString('if (requiredErrors.length)', $javascript);
        self::assertStringContainsString('form.addEventListener(\'submit\', (event) => { event.preventDefault(); calculate(true); });', $javascript);
    }

    private function readProjectFile(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($contents);

        return $contents;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function filesIn(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            $absolutePath = dirname(__DIR__, 2) . '/' . $path;
            if (is_file($absolutePath)) {
                $files[] = $absolutePath;

                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolutePath));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
