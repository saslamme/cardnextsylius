<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Sylius\Bundle\ProductBundle\Form\Type\ProductTranslationType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductTranslationTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('searchSynonyms', TextareaType::class, [
            'required' => false,
            'label' => 'Suchsynonyme',
            'help' => 'Alternative Suchbegriffe für dieses Produkt. Ein Begriff pro Zeile oder durch Komma/Semikolon getrennt. Die Begriffe werden nicht im Frontend angezeigt.',
            'attr' => [
                'rows' => 5,
                'placeholder' => "Ausweisdrucker\nPlastikkartendrucker\nBadge Printer",
            ],
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductTranslationType::class];
    }
}
