<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class LoginTemplateTest extends TestCase
{
    public function testLoginPanelsKeepTheirContentWithoutDecorativeAccountLabels(): void
    {
        $login = $this->readProjectFile('templates/bundles/SyliusShopBundle/account/login/content/login_container.html.twig');
        $register = $this->readProjectFile('templates/bundles/SyliusShopBundle/account/login/content/register_container.html.twig');

        self::assertStringContainsString('class="cn-auth__form-panel"', $login);
        self::assertStringContainsString("{% hook 'login_container' %}", $login);
        self::assertStringNotContainsString('cn-kicker', $login);
        self::assertStringNotContainsString('cardnext_channel_branding', $login);
        self::assertStringNotContainsString('cardnext.storefront.account.brand_account', $login);

        self::assertStringContainsString('class="cn-auth__aside"', $register);
        self::assertStringContainsString("{% hook 'register_container' %}", $register);
        self::assertStringNotContainsString('cn-auth__index', $register);
        self::assertStringNotContainsString('01 / ACCOUNT', $register);
    }

    public function testRegistrationPanelContentIsCenteredBySharedAuthStyles(): void
    {
        $styles = $this->readProjectFile('assets/shop/styles/cardnext.css');

        self::assertMatchesRegularExpression(
            '/\.cn-auth__aside\s*\{[^}]*align-items:\s*center;[^}]*text-align:\s*center;/s',
            $styles,
        );
        self::assertMatchesRegularExpression(
            '/\.cn-auth__aside svg\s*\{[^}]*display:\s*block;[^}]*max-width:\s*100%;[^}]*margin-inline:\s*auto;/s',
            $styles,
        );
    }

    private function readProjectFile(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($contents);

        return $contents;
    }
}
