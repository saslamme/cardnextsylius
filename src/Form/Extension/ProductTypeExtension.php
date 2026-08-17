<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Entity\Product\Manufacturer;
use App\Entity\Product\Product;
use App\Enum\Product\ProductKind;
use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

final class ProductTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $addProductKind = static function ($form, ?Product $product): void {
            $form->add('productKind', ChoiceType::class, [
                'label' => 'Produkttyp',
                'choices' => [
                    'Standardprodukt' => ProductKind::STANDARD,
                    'Konfigurationsprodukt' => ProductKind::CONFIGURABLE,
                ],
                'choice_value' => static fn (?ProductKind $kind): ?string => $kind?->value,
                'disabled' => $product?->getId() !== null,
                'help' => $product?->getId() !== null
                    ? 'Der Produkttyp ist nach der Erstellung gesperrt, damit keine Konfigurationsdaten verloren gehen.'
                    : 'Konfigurationsprodukte erhalten beim Speichern automatisch genau einen Konfigurator.',
            ]);
        };
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($addProductKind): void {
            $data = $event->getData();
            $addProductKind($event->getForm(), $data instanceof Product ? $data : null);
        });

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
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }
}
