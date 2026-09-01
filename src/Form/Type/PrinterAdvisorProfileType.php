<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Product\PrinterAdvisorProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PrinterAdvisorProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, ['required' => false, 'label' => 'Im Kartendrucker-Berater verwenden'])
            ->add('minAnnualVolume', IntegerType::class, [
                // Imported profiles can contain an empty form value. Keep null
                // from reaching the int-only setter by restoring its domain default.
                'empty_data' => '0',
                'label' => 'Mindest-Druckvolumen pro Jahr',
                'attr' => ['min' => 0],
            ])
            ->add('maxAnnualVolume', IntegerType::class, ['required' => false, 'label' => 'Maximales Druckvolumen (leer = unbegrenzt)', 'attr' => ['min' => 1]])
            ->add('singleSided', CheckboxType::class, ['required' => false, 'label' => 'Einseitiger Druck'])
            ->add('duplex', CheckboxType::class, ['required' => false, 'label' => 'Automatischer Duplexdruck'])
            ->add('magneticStripe', CheckboxType::class, ['required' => false, 'label' => 'Magnetstreifen-Kodierung'])
            ->add('contactChip', CheckboxType::class, ['required' => false, 'label' => 'Kontaktchip-Kodierung'])
            ->add('rfidNfc', CheckboxType::class, ['required' => false, 'label' => 'RFID / NFC'])
            ->add('directPrinting', CheckboxType::class, ['required' => false, 'label' => 'Standard-Direktkartendruck'])
            ->add('retransfer', CheckboxType::class, ['required' => false, 'label' => 'Retransfer / randlos'])
            ->add('lamination', CheckboxType::class, ['required' => false, 'label' => 'Laminierung möglich'])
            ->add('highDurability', CheckboxType::class, ['required' => false, 'label' => 'Für besonders haltbare Karten'])
            ->add('performanceClass', IntegerType::class, [
                'empty_data' => '1',
                'label' => 'Geschwindigkeitsklasse (1–5)',
                'attr' => ['min' => 1, 'max' => 5],
            ])
            ->add('priority', IntegerType::class, [
                'empty_data' => '0',
                'label' => 'Business-Priorität (−100 bis 100)',
                'attr' => ['min' => -100, 'max' => 100],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PrinterAdvisorProfile::class,
            'label' => 'Kartendrucker-Berater',
            'empty_data' => static fn (): PrinterAdvisorProfile => new PrinterAdvisorProfile(),
        ]);
    }
}
