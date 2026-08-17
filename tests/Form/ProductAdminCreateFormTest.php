<?php

declare(strict_types=1);

namespace Tests\Form;

use App\Entity\Product\Product;
use App\Entity\Product\ProductTranslation;
use App\Enum\Product\ProductKind;
use App\Form\Extension\ProductTranslationTypeExtension;
use App\Form\Extension\ProductTypeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Forms;

final class ProductAdminCreateFormTest extends TestCase
{
    public function testNewProductDefaultsToStandardAndAnEmptySubmissionCannotMapNull(): void
    {
        $product = new Product();
        $form = $this->createProductKindForm($product);

        self::assertSame(ProductKind::STANDARD, $product->getProductKind());
        self::assertSame(ProductKind::STANDARD, $form->get('productKind')->getData());
        self::assertNull($form->get('productKind')->getConfig()->getOption('placeholder'));
        self::assertTrue($form->get('productKind')->getConfig()->getOption('required'));
        self::assertTrue($form->get('productKind')->isDisabled());

        $form->submit(['productKind' => '']);

        self::assertTrue($form->isSynchronized());
        self::assertSame(ProductKind::STANDARD, $product->getProductKind());
    }

    public function testStandardCreateFlowDoesNotTrustSubmittedConfigurableKind(): void
    {
        $product = new Product();
        $form = $this->createProductKindForm($product);

        $form->submit(['productKind' => ProductKind::CONFIGURABLE->value]);

        self::assertTrue($form->isSynchronized());
        self::assertSame(ProductKind::STANDARD, $product->getProductKind());
    }

    public function testPersistedProductKindRemainsDisabledAndCannotBeChanged(): void
    {
        $product = new Product();
        (new \ReflectionProperty($product, 'id'))->setValue($product, 42);
        $form = $this->createProductKindForm($product);

        self::assertTrue($form->get('productKind')->isDisabled());

        $form->submit(['productKind' => ProductKind::CONFIGURABLE->value]);

        self::assertSame(ProductKind::STANDARD, $product->getProductKind());
    }

    public function testConfiguratorPathIsMappedWhileCreatingAConfigurableProduct(): void
    {
        $product = new Product();
        $product->setProductKind(ProductKind::CONFIGURABLE);
        $translation = new ProductTranslation();
        $translation->setLocale('de_DE');
        $translation->setTranslatable($product);
        $form = $this->createConfiguratorPathForm($translation);

        self::assertTrue($form->has('configuratorPath'));
        self::assertTrue($form->get('configuratorPath')->getConfig()->getOption('required'));

        $form->submit(['configuratorPath' => '/plastikkarten/plastikkarten-bedrucken']);

        self::assertTrue($form->isSynchronized());
        self::assertSame('plastikkarten/plastikkarten-bedrucken', $translation->getConfiguratorPath());
    }

    public function testEmptyConfiguratorPathCanBeSubmittedWithoutAMappingException(): void
    {
        $product = new Product();
        $product->setProductKind(ProductKind::CONFIGURABLE);
        $translation = new ProductTranslation();
        $translation->setLocale('de_DE');
        $translation->setTranslatable($product);
        $form = $this->createConfiguratorPathForm($translation);

        $form->submit(['configuratorPath' => '']);

        self::assertTrue($form->isSynchronized());
        self::assertNull($translation->getConfiguratorPath());
    }

    public function testConfiguratorPathVisibilityForPersistedProductsFollowsTheirKind(): void
    {
        $standard = new Product();
        (new \ReflectionProperty($standard, 'id'))->setValue($standard, 42);
        $standardTranslation = new ProductTranslation();
        $standardTranslation->setLocale('de_DE');
        $standardTranslation->setTranslatable($standard);

        self::assertFalse($this->createConfiguratorPathForm($standardTranslation)->has('configuratorPath'));

        $configurable = new Product();
        $configurable->setProductKind(ProductKind::CONFIGURABLE);
        (new \ReflectionProperty($configurable, 'id'))->setValue($configurable, 43);
        $configurableTranslation = new ProductTranslation();
        $configurableTranslation->setLocale('de_DE');
        $configurableTranslation->setTranslatable($configurable);
        $form = $this->createConfiguratorPathForm($configurableTranslation);

        self::assertTrue($form->has('configuratorPath'));
        self::assertTrue($form->get('configuratorPath')->getConfig()->getOption('required'));
    }

    public function testConfiguratorPathIsAbsentFromNewStandardProduct(): void
    {
        $product = new Product();
        $translation = new ProductTranslation();
        $translation->setLocale('de_DE');
        $translation->setTranslatable($product);

        self::assertFalse($this->createConfiguratorPathForm($translation)->has('configuratorPath'));
    }

    private function createProductKindForm(Product $product): \Symfony\Component\Form\FormInterface
    {
        $listener = $this->capturePreSetDataListener(new ProductTypeExtension());
        $form = Forms::createFormFactory()->createBuilder(FormType::class, $product)->getForm();
        $listener(new FormEvent($form, $product));

        return $form;
    }

    private function createConfiguratorPathForm(ProductTranslation $translation): \Symfony\Component\Form\FormInterface
    {
        $listener = $this->capturePreSetDataListener(new ProductTranslationTypeExtension());
        $form = Forms::createFormFactory()->createBuilder(FormType::class, $translation)->getForm();
        $listener(new FormEvent($form, $translation));

        return $form;
    }

    private function capturePreSetDataListener(ProductTypeExtension|ProductTranslationTypeExtension $extension): callable
    {
        $listener = null;
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder
            ->expects(self::once())
            ->method('addEventListener')
            ->with(FormEvents::PRE_SET_DATA, self::callback(static function (callable $callback) use (&$listener): bool {
                $listener = $callback;

                return true;
            }))
            ->willReturnSelf();

        $extension->buildForm($builder, []);
        self::assertIsCallable($listener);

        return $listener;
    }
}
