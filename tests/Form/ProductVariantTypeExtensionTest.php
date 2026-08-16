<?php

declare(strict_types=1);

namespace Tests\Form;

use App\Entity\Product\ProductVariant;
use App\Form\Extension\ProductVariantTypeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class ProductVariantTypeExtensionTest extends TestCase
{
    #[DataProvider('quantityFieldProvider')]
    public function testEmptyQuantityFieldsFallBackToTheDatabaseAndEntityDefault(string $field): void
    {
        $quantityOptions = null;
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->method('add')
            ->willReturnCallback(static function (string $name, ?string $type = null, array $options = []) use ($builder, $field, &$quantityOptions): FormBuilderInterface {
                if ($name === $field) {
                    self::assertSame(IntegerType::class, $type);
                    $quantityOptions = $options;
                }

                return $builder;
            });

        (new ProductVariantTypeExtension())->buildForm($builder, []);

        self::assertIsArray($quantityOptions);
        self::assertSame('1', $quantityOptions['empty_data']);

        $variant = new ProductVariant();
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->createBuilder(FormType::class, $variant)
            ->add($field, IntegerType::class, $quantityOptions)
            ->getForm();

        $form->submit([$field => '']);

        self::assertTrue($form->isSubmitted());
        self::assertSame(1, match ($field) {
            'minimumOrderQuantity' => $variant->getMinimumOrderQuantity(),
            'orderIncrement' => $variant->getOrderIncrement(),
            'packQuantity' => $variant->getPackQuantity(),
        });
    }

    /** @return iterable<string, array{string}> */
    public static function quantityFieldProvider(): iterable
    {
        yield 'minimum order quantity from the production exception' => ['minimumOrderQuantity'];
        yield 'order increment' => ['orderIncrement'];
        yield 'pack quantity' => ['packQuantity'];
    }
}
