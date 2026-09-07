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

    public function testAccountLayoutRendersTranslatedFlashMessagesOnce(): void
    {
        $layout = (string) file_get_contents(__DIR__ . '/../../templates/shop/account/_layout.html.twig');
        $flashes = (string) file_get_contents(__DIR__ . '/../../templates/shop/account/_flashes.html.twig');

        self::assertSame(1, substr_count($layout, "include 'shop/account/_flashes.html.twig'"));
        self::assertStringContainsString('app.flashes(type)', $flashes);
        self::assertStringContainsString('message|trans', $flashes);
        self::assertStringContainsString('role="alert"', $flashes);
        self::assertStringContainsString("error: { alert: 'alert-danger'", $flashes);
    }

    public function testCreateFormProvidesSpecificFeedbackWithoutChangingFailureFlow(): void
    {
        $controller = (string) file_get_contents(__DIR__ . '/../../src/Controller/Shop/SupportAccountController.php');
        $formType = (string) file_get_contents(__DIR__ . '/../../src/Form/Type/SupportCaseCreateType.php');
        $template = (string) file_get_contents(__DIR__ . '/../../templates/shop/account/support/new.html.twig');

        self::assertStringContainsString('Response::HTTP_UNPROCESSABLE_ENTITY', $controller);
        self::assertStringContainsString('Response::HTTP_SERVICE_UNAVAILABLE', $controller);
        self::assertStringContainsString("addFlash('success', 'cardnext.support.flash.created')", $controller);
        self::assertStringContainsString('cardnext.support.validation.subject_required', $formType);
        self::assertStringContainsString('cardnext.support.validation.description_short', $formType);
        self::assertStringContainsString('cardnext.support.validation.summary', $template);
        self::assertStringContainsString('form_errors(field)', $template);
        self::assertStringContainsString('service_unavailable', $template);
    }
}
