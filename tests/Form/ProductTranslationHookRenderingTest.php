<?php

declare(strict_types=1);

namespace App\Tests\Form;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class ProductTranslationHookRenderingTest extends TestCase
{
    public function testCreateAndUpdateHooksRenderConfiguratorPathBetweenSlugAndDescription(): void
    {
        $configuration = Yaml::parseFile(__DIR__ . '/../../config/packages/cardnext_twig_hooks.yaml');
        $hooks = $configuration['sylius_twig_hooks']['hooks'];

        foreach (['create', 'update'] as $action) {
            $hook = $hooks['sylius_admin.product.' . $action . '.content.form.sections.translations'];
            self::assertSame(350, $hook['configurator_path']['priority']);
            self::assertSame('admin/product/form/sections/translations/configurator_path.html.twig', $hook['configurator_path']['template']);
            self::assertGreaterThan($hook['configurator_path']['priority'], 400);
            self::assertLessThan($hook['configurator_path']['priority'], 300);
        }
    }

    public function testHookTemplateRendersARealFormRowDefensively(): void
    {
        $template = file_get_contents(__DIR__ . '/../../templates/admin/product/form/sections/translations/configurator_path.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('form.configuratorPath is defined', $template);
        self::assertStringContainsString('form_row(form.configuratorPath)', $template);
    }

    public function testHookTemplateProducesTheLocaleAwareSymfonyInput(): void
    {
        $builder = Forms::createFormFactory()->createNamedBuilder('sylius_admin_product', FormType::class);
        $builder->add('translations', FormType::class);
        $builder->get('translations')->add('de_DE', FormType::class);
        $builder->get('translations')->get('de_DE')->add('configuratorPath', TextType::class);
        $form = $builder->getForm();

        $twig = new Environment(new FilesystemLoader([
            __DIR__ . '/../../templates',
            __DIR__ . '/../../vendor/symfony/twig-bridge/Resources/views/Form',
        ]));
        $twig->addExtension(new FormExtension());
        $twig->addExtension(new TranslationExtension(new Translator('en')));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            \Symfony\Component\Form\FormRenderer::class => static fn (): \Symfony\Component\Form\FormRenderer => new \Symfony\Component\Form\FormRenderer(new TwigRendererEngine(['form_div_layout.html.twig'], $twig)),
        ]));

        $html = $twig->render('admin/product/form/sections/translations/configurator_path.html.twig', [
            'form' => $form->createView()['translations']['de_DE'],
        ]);

        self::assertStringContainsString('name="sylius_admin_product[translations][de_DE][configuratorPath]"', $html);
        self::assertStringContainsString('<input', $html);
    }
}
