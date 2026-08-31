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
        self::assertStringContainsString("form.addEventListener('change', configurationChanged)", $javascript);
        self::assertStringContainsString('const debouncedCalculate = debounce(calculate, 450);', $javascript);
    }

    public function testConfiguratorUsesReferenceControlsAndAutomaticServerPricing(): void
    {
        $template = $this->readProjectFile('templates/shop/configurator/product.html.twig');
        $javascript = $this->readProjectFile('assets/shop/configurator.js');

        self::assertStringContainsString('<select class="cn-configurator__control"', $template);
        self::assertStringContainsString('cn-configurator__segmented', $template);
        self::assertStringContainsString('cn-configurator__choices', $template);
        self::assertStringContainsString("field.type.value == 'boolean'", $template);
        self::assertStringContainsString('cn-configurator__buybar', $template);
        self::assertStringContainsString('cn-configurator__price', $template);
        self::assertStringNotContainsString('data-configurator-submit', $template);
        self::assertStringNotContainsString('Preis berechnen', $template);
        self::assertStringNotContainsString('cn-step', $template);
        self::assertStringNotContainsString('cn-presets', $template);
        self::assertStringContainsString("control.tagName === 'SELECT'", $javascript);
        self::assertStringContainsString('calculatedPayload = undefined', $javascript);
        self::assertStringContainsString('data.total / 100', $javascript);
        self::assertStringNotContainsString('quantityBase', $javascript);
    }

    public function testAllChoicePresentationsRenderConfiguredPreselections(): void
    {
        $template = $this->readProjectFile('templates/shop/configurator/product.html.twig');

        self::assertStringContainsString('{% if value.preselected %} selected{% endif %}', $template);
        self::assertStringContainsString('{% if value.preselected %} checked{% endif %}', $template);
        self::assertStringContainsString("field.type.value == 'single_choice' and activeValues|length == 2", $template);
        self::assertStringContainsString('visualValues = activeValues|filter(value => value.imagePath or value.colorHex or value.icon)', $template);
        self::assertStringContainsString("field.type.value == 'multiple_choice' ? '[]' : ''", $template);
        self::assertStringContainsString('<option value="">', $template, 'The empty option must remain when no default is configured.');
    }

    public function testDependenciesSanitizeDefaultsBeforeInitialServerCalculation(): void
    {
        $javascript = $this->readProjectFile('assets/shop/configurator.js');

        $initialDependencies = strpos($javascript, "    applyDependencies();\n\n    const clearErrors");
        $initialCalculation = strpos($javascript, 'updateSelectionSummary(); debouncedCalculate();');
        self::assertIsInt($initialDependencies);
        self::assertIsInt($initialCalculation);
        self::assertLessThan($initialCalculation, $initialDependencies);
        self::assertStringContainsString('if (!controlIsEffective(control)) clearControl(control);', $javascript);
        self::assertStringContainsString('fetch(root.dataset.endpoint', $javascript);
        self::assertStringNotContainsString('ConfiguratorPriceCalculator', $javascript);
    }

    public function testConfiguratorHeroWithoutImageUsesOneColumn(): void
    {
        $template = $this->readProjectFile('templates/shop/configurator/page.html.twig');
        $stylesheet = $this->readProjectFile('assets/shop/styles/cardnext.css');

        self::assertStringContainsString("configurator.images|length > 0 ? ' cn-configurator-hero--with-image' : ''", $template);
        self::assertStringContainsString('.cn-configurator-page .cn-configurator-hero { display: grid; grid-template-columns: minmax(0, 1fr);', $stylesheet);
        self::assertStringContainsString('.cn-configurator-page .cn-configurator-hero--with-image { grid-template-columns: 156px minmax(0, 1fr); }', $stylesheet);
        self::assertStringContainsString('.cn-configurator-page .cn-configurator-hero--with-image { grid-template-columns: 78px minmax(0,1fr); }', $stylesheet);
    }

    public function testConfiguratorProcessMatchesApprovedInlineIllustrationLayout(): void
    {
        $template = $this->readProjectFile('templates/shop/configurator/page.html.twig');
        $stylesheet = $this->readProjectFile('assets/shop/styles/cardnext.css');
        $germanTranslations = $this->readProjectFile('translations/messages.de.yaml');
        $englishTranslations = $this->readProjectFile('translations/messages.en.yaml');

        self::assertStringContainsString('<section class="cn-configurator-process"', $template);
        self::assertSame(5, substr_count($template, 'class="cn-configurator-process__step"'));
        self::assertSame(5, substr_count($template, '<svg aria-hidden="true"'));
        self::assertStringContainsString('fill="#20272D"', $template);
        self::assertStringContainsString('fill="#E95126"', $template);
        $processOffset = strpos($template, '<section class="cn-configurator-process"');
        self::assertIsInt($processOffset);
        self::assertStringNotContainsString('<img', substr($template, $processOffset));
        self::assertStringNotContainsString('font-awesome', strtolower($template));
        self::assertStringNotContainsString('bootstrap-icons', strtolower($template));

        foreach (range(1, 5) as $step) {
            self::assertStringContainsString(sprintf('process.step_%d.description', $step), $template);
            self::assertStringContainsString(sprintf('      step_%d:', $step), $germanTranslations);
            self::assertStringContainsString(sprintf('      step_%d:', $step), $englishTranslations);
        }

        self::assertStringContainsString('background: #eef0f1;', $stylesheet);
        self::assertStringContainsString('grid-template-columns: repeat(5, minmax(0, 1fr));', $stylesheet);
        self::assertStringNotContainsString('border-top: 1px solid rgba(255,255,255,.24)', $stylesheet);
        $processCssOffset = strpos($stylesheet, '.cn-configurator-process {');
        self::assertIsInt($processCssOffset);
        self::assertStringNotContainsString('background: var(--cn-ink);', substr($stylesheet, $processCssOffset, 250));
        self::assertStringNotContainsString('process.kicker', $template);
    }

    public function testConfiguredLeadTimeAndFixedNumericDefaultsUseTheExistingInitialCalculation(): void
    {
        $template = $this->readProjectFile('templates/shop/configurator/product.html.twig');
        $javascript = $this->readProjectFile('assets/shop/configurator.js');

        self::assertStringContainsString('{% if leadTime.preselected %} checked{% endif %}', $template);
        self::assertStringNotContainsString('activeLeadTimes|first', $template);
        self::assertStringContainsString('field.minimumValue == field.maximumValue', $template);
        self::assertStringContainsString('value="{{ field.minimumValue }}" readonly', $template);
        self::assertStringNotContainsString('value="{{ field.minimumValue }}" disabled', $template);
        self::assertStringContainsString("root.querySelector('input[name=\"leadTimeCode\"]:checked')?.value", $javascript);
        self::assertStringContainsString('selections[field.dataset.configuratorField] = value', $javascript);
    }

    public function testNormalNumericInputsRemainEditable(): void
    {
        $template = $this->readProjectFile('templates/shop/configurator/product.html.twig');

        self::assertStringContainsString('{% if fixedNumericValue %} value="{{ field.minimumValue }}" readonly{% endif %}', $template);
        self::assertStringNotContainsString('readonly{% endif %} disabled', $template);
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
                self::assertInstanceOf(\SplFileInfo::class, $file);
                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
