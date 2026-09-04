<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Entity\Product\Manufacturer;
use App\Form\Type\PrinterAdvisorProfileType;
use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Form\Type\ProductBundleType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

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
            'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('manufacturer')
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
            ->add('addonOnly', CheckboxType::class, [
                'required' => false,
                'label' => 'Nur als Zusatzleistung',
                'help' => 'Dieses Produkt kann nur zusammen mit einem zugeordneten Hauptprodukt gekauft werden und wird nicht im normalen Produktsortiment angezeigt.',
            ])
            ->add('homepageFeatured', CheckboxType::class, [
                'required' => false,
                'label' => 'Auf Startseite anzeigen',
                'help' => 'Zeigt das Produkt im Bereich „Ausgewählte Produkte“ an.',
            ])
            ->add('homepagePosition', IntegerType::class, [
                'required' => true,
                // Imported products and requests created before this field was
                // introduced can submit an empty value. Without an explicit
                // default, the form mapper passes null to the int-only entity
                // setter and turns an otherwise recoverable validation case
                // into a 500 response.
                'empty_data' => '100',
                'label' => 'Startseiten-Position',
                'help' => 'Kleinere Zahlen werden zuerst angezeigt.',
                'constraints' => [
                    new PositiveOrZero(),
                ],
                'attr' => [
                    'min' => 0,
                    'step' => 1,
                ],
            ]);

        $builder->add('printerAdvisorProfile', PrinterAdvisorProfileType::class, [
            'required' => false,
            'label' => 'Kartendrucker-Berater',
        ]);

        $builder->add('bundles', CollectionType::class, [
            'entry_type' => ProductBundleType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'label' => 'Bundles / Häufig zusammen gekauft',
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }
}
