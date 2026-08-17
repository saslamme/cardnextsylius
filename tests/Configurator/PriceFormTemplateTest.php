<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use PHPUnit\Framework\TestCase;

final class PriceFormTemplateTest extends TestCase
{
    public function testDependentSelectsAreDisabledWhenTheirValuesAreIrrelevant(): void
    {
        $template = file_get_contents(__DIR__ . '/../../templates/admin/cardnext/configurator/price_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('percentageBaseSelect.disabled=!isPercent', $template);
        self::assertStringContainsString('multiplierFieldSelect.disabled=!usesMultiplierField', $template);
        self::assertStringContainsString("const usesMultiplierField=m.value==='field_value'", $template);
        self::assertStringContainsString("const isPercent=pt.value==='percent'", $template);
        self::assertStringContainsString("o.value==='quantity'&&pt.value!=='fixed'", $template);
        self::assertStringContainsString("if(m.selectedOptions[0].hidden)m.value='none'", $template);
        self::assertStringContainsString("m.addEventListener('change',t);t()", $template);
    }
}
