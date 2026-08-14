<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Product\Manufacturer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class ManufacturerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'help' => 'Stabiler interner Code, z. B. EVOLIS oder HID_FARGO.',
            ])
            ->add('name', TextType::class, [
                'label' => 'Name',
            ])
            ->add('website', UrlType::class, [
                'label' => 'Website',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschreibung',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Sortierung',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Aktiv',
                'required' => false,
            ])
            ->add('logo', FileType::class, [
                'label' => 'Logo',
                'mapped' => false,
                'required' => false,
                'help' => 'PNG, JPG oder WebP; maximal 5 MB.',
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                        mimeTypesMessage: 'Bitte PNG, JPG oder WebP hochladen.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Manufacturer::class,
        ]);
    }
}
