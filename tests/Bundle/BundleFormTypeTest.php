<?php

declare(strict_types=1);

namespace App\Tests\Bundle;

use App\Entity\Product\ProductBundle;
use App\Entity\Product\ProductBundleChannel;
use App\Entity\Product\ProductBundleItem;
use App\Form\Type\ProductBundleChannelType;
use App\Form\Type\ProductBundleItemType;
use App\Form\Type\ProductBundleType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class BundleFormTypeTest extends TestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }

    public function testEmptyBundlePositionUsesDomainDefaultWithoutTypeError(): void
    {
        $bundle = new ProductBundle();
        $form = $this->createPrunedForm(new ProductBundleType(), $bundle, ['code', 'name', 'enabled', 'items', 'channelConfigurations']);

        $form->submit(['position' => '']);

        self::assertTrue($form->get('position')->isValid());
        self::assertSame(10, $bundle->getPosition());
    }

    public function testEmptyItemNumbersUseDomainDefaultsWithoutTypeError(): void
    {
        $item = new ProductBundleItem();
        $form = $this->createPrunedForm(new ProductBundleItemType(), $item, ['variant', 'enabled']);

        $form->submit(['quantity' => '', 'position' => '']);

        self::assertTrue($form->get('quantity')->isValid());
        self::assertTrue($form->get('position')->isValid());
        self::assertSame(1, $item->getQuantity());
        self::assertSame(10, $item->getPosition());
    }

    public function testNegativePositionsAndZeroQuantityProduceFormErrors(): void
    {
        $bundleForm = $this->createPrunedForm(new ProductBundleType(), new ProductBundle(), ['code', 'name', 'enabled', 'items', 'channelConfigurations']);
        $bundleForm->submit(['position' => '-1']);

        $itemForm = $this->createPrunedForm(new ProductBundleItemType(), new ProductBundleItem(), ['variant', 'enabled']);
        $itemForm->submit(['quantity' => '0', 'position' => '-1']);

        self::assertFalse($bundleForm->get('position')->isValid());
        self::assertFalse($itemForm->get('quantity')->isValid());
        self::assertFalse($itemForm->get('position')->isValid());
    }

    public function testEmptyDiscountTypeAndUncheckedFlagsMapToSafeValues(): void
    {
        $channel = new ProductBundleChannel();
        $form = $this->createPrunedForm(new ProductBundleChannelType(), $channel, ['channel', 'fixedDiscount', 'percentageDiscount']);

        $form->submit(['discountType' => '']);

        self::assertSame(ProductBundleChannel::DISCOUNT_NONE, $channel->getDiscountType());
        self::assertFalse($channel->isEnabled());
    }

    public function testUncheckedBundleAndItemFlagsMapToFalse(): void
    {
        $bundle = new ProductBundle();
        $bundleForm = $this->createPrunedForm(new ProductBundleType(), $bundle, ['code', 'name', 'position', 'items', 'channelConfigurations']);
        $bundleForm->submit([]);

        $item = new ProductBundleItem();
        $itemForm = $this->createPrunedForm(new ProductBundleItemType(), $item, ['variant', 'quantity', 'position']);
        $itemForm->submit([]);

        self::assertFalse($bundle->isEnabled());
        self::assertFalse($item->isEnabled());
    }

    public function testInvalidDiscountTypeProducesAFormErrorWithoutCallingTheSetter(): void
    {
        $channel = new ProductBundleChannel();
        $form = $this->createPrunedForm(new ProductBundleChannelType(), $channel, ['channel', 'enabled', 'fixedDiscount', 'percentageDiscount']);

        $form->submit(['discountType' => 'INVALID']);

        self::assertFalse($form->get('discountType')->isValid());
        self::assertSame(ProductBundleChannel::DISCOUNT_NONE, $channel->getDiscountType());
    }

    /**
     * Build the production type while removing unrelated children before Symfony
     * resolves them. This keeps these regression tests independent of Doctrine.
     *
     * @param list<string> $fieldsToRemove
     */
    private function createPrunedForm(AbstractType $type, object $data, array $fieldsToRemove): FormInterface
    {
        $builder = $this->formFactory->createBuilder(FormType::class, $data, [
            'data_class' => $data::class,
        ]);
        $type->buildForm($builder, []);

        foreach ($fieldsToRemove as $name) {
            $builder->remove($name);
        }

        return $builder->getForm();
    }
}
