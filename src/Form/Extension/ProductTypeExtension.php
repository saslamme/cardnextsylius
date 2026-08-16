<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Entity\Product\Manufacturer;
use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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

        $builder->add('model', TextType::class, [
            'required' => false,
            'label' => 'Modell',
            'help' => 'Modell- bzw. Produktfamilienbezeichnung, z. B. Primacy 2, ZC300 oder TWN4 MultiTech 2.',
            'attr' => [
                'maxlength' => 255,
                'autocomplete' => 'off',
            ],
        ]);

        $builder->add('dataQualityStatus', ChoiceType::class, [
            'required' => true,
            'label' => 'Datenstatus',
            'choices' => [
                'Importiert / ungeprüft' => 'imported',
                'Prüfung erforderlich' => 'needs_review',
                'Verifiziert' => 'verified',
            ],
            'help' => 'Kennzeichnet, ob die technischen Produktdaten bereits geprüft wurden.',
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
