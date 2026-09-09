<?php

declare(strict_types=1);

namespace Tests\Form;

use App\Form\Extension\ProductTranslationTypeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductTranslationTypeExtensionTest extends TestCase
{
    public function testItAddsSearchSynonymsToEveryTranslationForm(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::once())->method('add')->with(
            'searchSynonyms',
            TextareaType::class,
            self::callback(static fn (array $options): bool => $options['required'] === false &&
                $options['label'] === 'Suchsynonyme' &&
                str_contains($options['help'], 'Komma/Semikolon') &&
                $options['attr']['rows'] === 5 &&
                str_contains($options['attr']['placeholder'], "\n")),
        )->willReturn($builder);

        (new ProductTranslationTypeExtension())->buildForm($builder, []);
    }
}
