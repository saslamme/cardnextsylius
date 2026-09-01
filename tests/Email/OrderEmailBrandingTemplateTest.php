<?php

declare(strict_types=1);

namespace App\Tests\Email;

use PHPUnit\Framework\TestCase;

final class OrderEmailBrandingTemplateTest extends TestCase
{
    public function testOrderConfirmationUsesExplicitEmailBrandingAndRealLogo(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/bundles/SyliusShopBundle/email/order_confirmation.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString('cardnext_channel_email_branding(channel)', $template);
        self::assertStringContainsString('emailBranding.logoUrl', $template);
        self::assertStringNotContainsString('>cardne</span>', $template);
    }

    public function testInternalNotificationUsesOrderChannel(): void
    {
        $listener = file_get_contents(dirname(__DIR__, 2) . '/src/EventListener/InternalOrderNotificationListener.php');
        self::assertIsString($listener);
        self::assertStringContainsString('$order->getChannel()', $listener);
        self::assertStringContainsString("'Neue %s-Bestellung %s'", $listener);
        self::assertStringNotContainsString("'Neue Cardnext-Bestellung", $listener);
    }
}
