<?php

declare(strict_types=1);

namespace App\Form\Extension\Admin;

use Sylius\Bundle\TaxonomyBundle\Form\Type\TaxonTranslationType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

#[AutoconfigureTag('form.type_extension')]
final class TaxonTranslationTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('bottomDescription', TextareaType::class, [
            'required' => false,
            'label' => 'Kategoriebeschreibung unter Produktliste',
            'help' => 'Wird auf der Kategorieseite unterhalb der Produktliste angezeigt.',
            'attr' => [
                'rows' => 10,
            ],
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [TaxonTranslationType::class];
    }
}
