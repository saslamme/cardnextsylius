<?php

declare(strict_types=1);

namespace Tests\Form;

use App\Form\Extension\ProductTypeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

final class ProductTypeExtensionTest extends TestCase
{
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
