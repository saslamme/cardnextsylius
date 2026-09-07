<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\TestCase;

final class SupportPortalArchitectureTest extends TestCase
{
    public function testIndexDoesNotUseFreshdeskAndAllPagesApplyPrivateHeaders(): void
    {
        $controller = (string) file_get_contents(__DIR__ . '/../../src/Controller/Shop/SupportAccountController.php');
        $index = substr($controller, strpos($controller, 'public function index'), strpos($controller, '#[Route(\'/new\'') - strpos($controller, 'public function index'));

        self::assertStringNotContainsString('Freshdesk', $index);
        self::assertStringContainsString("'Cache-Control', 'private, no-store'", $controller);
        self::assertStringContainsString("'X-Robots-Tag', 'noindex, nofollow, noarchive'", $controller);
        self::assertStringContainsString('findForCustomerAndChannel', $controller);
    }

    public function testCustomerFacingTemplateOnlyRendersFilteredConversationText(): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../../templates/shop/account/support/show.html.twig');

        self::assertStringContainsString('conversation.bodyText', $template);
        self::assertStringNotContainsString('bodyHtml', $template);
        self::assertStringNotContainsString('|raw', $template);
        self::assertStringNotContainsString('private', $template);
    }
}
