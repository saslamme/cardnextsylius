<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Entity\Product\Manufacturer;
use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('manufacturer', EntityType::class, [
            'class' => Manufacturer::class,
            'choice_label' => 'name',
            'placeholder' => '— Kein Hersteller —',
            'required' => false,
            'label' => 'Hersteller',
            'query_builder' => static fn (EntityRepository $repository) =>
                $repository->createQueryBuilder('manufacturer')
                    ->orderBy('manufacturer.position', 'ASC')
                    ->addOrderBy('manufacturer.name', 'ASC'),
        ]);

        $builder->add('manufacturerPartNumber', TextType::class, [
            'required' => false,
            'label' => 'Hersteller-Artikelnummer',
            'help' => 'Originale Artikelnummer des Herstellers, z. B. RDR-805W1BKU.',
            'attr' => [
                'maxlength' => 128,
                'autocomplete' => 'off',
            ],
        ]);

        $builder->add('ean', TextType::class, [
            'required' => false,
            'label' => 'EAN / GTIN',
            'help' => 'EAN, UPC oder GTIN des Artikels.',
            'attr' => [
                'maxlength' => 64,
                'inputmode' => 'numeric',
                'autocomplete' => 'off',
            ],
        ]);

        $builder
            ->add('homepageFeatured', CheckboxType::class, [
                'required' => false,
                'label' => 'Auf Startseite anzeigen',
                'help' => 'Zeigt das Produkt im Bereich „Ausgewählte Produkte“ an.',
            ])
            ->add('homepagePosition', IntegerType::class, [
                'required' => true,
                'label' => 'Startseiten-Position',
                'help' => 'Kleinere Zahlen werden zuerst angezeigt.',
                'attr' => [
                    'min' => 0,
                    'step' => 1,
                ],
            ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }
}
