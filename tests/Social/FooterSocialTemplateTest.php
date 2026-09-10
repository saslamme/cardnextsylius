<?php

declare(strict_types=1);

namespace App\Tests\Social;

use PHPUnit\Framework\TestCase;

final class FooterSocialTemplateTest extends TestCase
{
    public function testFooterRendersConfiguredSocialLinksSecurelyBetweenPaymentsAndShipping(): void
    {
        $template = file_get_contents(\dirname(__DIR__, 2) . '/templates/shop/layout/footer/content.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString('cardnext_footer_social_links()', $template);
        self::assertStringContainsString('{% if footerSocialLinks is not empty %}', $template);
        self::assertStringContainsString('target="_blank" rel="noopener noreferrer"', $template);
        self::assertLessThan(strpos($template, 'cn-footer__social'), strpos($template, 'cn-footer__payments'));
        self::assertLessThan(strpos($template, 'cn-footer__shipping'), strpos($template, 'cn-footer__social'));
    }
}
