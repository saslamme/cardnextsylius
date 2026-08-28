<?php

declare(strict_types=1);

namespace Tests\Form;

use App\Form\Extension\ProductTypeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

final class ProductTypeExtensionTest extends TestCase
{
    public function testItProvidesEveryFieldRenderedByTheCardnextProductSection(): void
    {
        $fieldTypes = [];
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->method('add')
            ->willReturnCallback(static function (string $name, ?string $type = null) use ($builder, &$fieldTypes): FormBuilderInterface {
                $fieldTypes[$name] = $type;

                return $builder;
            });

        (new ProductTypeExtension())->buildForm($builder, []);

        self::assertSame([
            'manufacturer' => EntityType::class,
            'model' => TextType::class,
            'dataQualityStatus' => ChoiceType::class,
            'homepageFeatured' => CheckboxType::class,
            'homepagePosition' => IntegerType::class,
        ], $fieldTypes);
    }

    public function testHomepagePositionFallsBackToAValidValueWhenSubmittedEmpty(): void
    {
        $homepagePositionOptions = null;
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->method('add')
            ->willReturnCallback(static function (string $name, ?string $type = null, array $options = []) use ($builder, &$homepagePositionOptions): FormBuilderInterface {
                if ($name === 'homepagePosition') {
                    self::assertSame(IntegerType::class, $type);
                    $homepagePositionOptions = $options;
                }

                return $builder;
            });

        (new ProductTypeExtension())->buildForm($builder, []);

        self::assertIsArray($homepagePositionOptions);
        self::assertSame('100', $homepagePositionOptions['empty_data']);
        self::assertContainsOnlyInstancesOf(PositiveOrZero::class, $homepagePositionOptions['constraints']);
    }
}
