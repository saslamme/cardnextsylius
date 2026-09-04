<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class CmsEditorialBlocksTemplateTest extends TestCase
{
    public function testFeaturesTemplateIsDefensiveAndMapsSemanticIcons(): void
    {
        $template = $this->template('_features.html.twig');
        self::assertStringContainsString('config.items|default([])', $template);
        self::assertStringContainsString('cn-features--{{ columns }}', $template);
        self::assertStringContainsString("consulting: 'fa-comments'", $template);
        self::assertStringContainsString('cn-feature__title', $template);
        self::assertStringContainsString('config.headline|default or config.text|default', $template);
        self::assertStringNotContainsString('|raw', $template);

        $html = $this->render('_features.html.twig', ['items' => [['title' => 'Beratung'], ['title' => 'Versand', 'icon' => 'shipping']], 'columns' => 2]);
        self::assertStringContainsString('cn-features--2', $html);
        self::assertStringContainsString('fa-truck-fast', $html);
        self::assertStringNotContainsString('cn-cms-features__header', $html);
    }

    public function testStatsTemplateRendersUnformattedEscapedValuesWithoutCounters(): void
    {
        $template = $this->template('_stats.html.twig');
        self::assertStringContainsString('cn-stats--{{ columns }}', $template);
        self::assertStringContainsString('{{ item.value }}', $template);
        self::assertStringContainsString('{{ item.label }}', $template);
        self::assertStringContainsString('item.description|default', $template);
        self::assertStringNotContainsString('|raw', $template);
        self::assertStringNotContainsString('data-counter', $template);
        self::assertStringNotContainsString('<script', $template);

        $html = $this->render('_stats.html.twig', ['items' => [['value' => '< 24 h', 'label' => 'Reaktionszeit']], 'columns' => 3]);
        self::assertStringContainsString('&lt; 24 h', $html);
        self::assertStringContainsString('cn-stats--3', $html);
    }

    public function testTestimonialsTemplateUsesSemanticEscapedMarkupAndCleanMetadata(): void
    {
        $template = $this->template('_testimonials.html.twig');
        self::assertStringContainsString('<article class="cn-testimonial">', $template);
        self::assertStringContainsString('<blockquote class="cn-testimonial__quote">', $template);
        self::assertStringContainsString('<cite>', $template);
        self::assertStringContainsString('aria-hidden="true"', $template);
        self::assertStringContainsString('item.role|default and item.company|default', $template);
        self::assertStringContainsString('cn-testimonials--{{ columns }}', $template);
        self::assertStringNotContainsString('|raw', $template);

        $html = $this->render('_testimonials.html.twig', ['items' => [['quote' => '<script>alert(1)</script>', 'name' => 'Max', 'role' => 'IT', 'company' => 'Beispiel GmbH']], 'columns' => 2]);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('IT · Beispiel GmbH', $html);
        self::assertStringNotContainsString('IT · </span>', $html);
    }

    private function template(string $file): string
    {
        $template = file_get_contents(__DIR__ . '/../../templates/shop/cms/block/' . $file);
        self::assertIsString($template);

        return $template;
    }

    /** @param array<string, mixed> $config */
    private function render(string $file, array $config): string
    {
        $twig = new Environment(new FilesystemLoader(__DIR__ . '/../../templates'), ['autoescape' => 'html']);

        return $twig->render('shop/cms/block/' . $file, ['config' => $config]);
    }
}
