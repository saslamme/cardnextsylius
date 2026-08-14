<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Product\ProductDocument;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class ProductDocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titel',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Dokumenttyp',
                'choices' => [
                    'Datenblatt' => ProductDocument::TYPE_DATASHEET,
                    'Handbuch' => ProductDocument::TYPE_MANUAL,
                    'Treiber / Software' => ProductDocument::TYPE_DRIVER,
                    'Zertifikat' => ProductDocument::TYPE_CERTIFICATE,
                    'Broschüre' => ProductDocument::TYPE_BROCHURE,
                    'Sonstiges' => ProductDocument::TYPE_OTHER,
                ],
            ])
            ->add('locale', TextType::class, [
                'label' => 'Sprache / Locale',
                'required' => false,
                'help' => 'Leer = in allen Sprachversionen sichtbar. Beispiel: de_DE.',
                'attr' => ['placeholder' => 'de_DE'],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Sortierung',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Aktiv',
                'required' => false,
            ])
            ->add('file', FileType::class, [
                'label' => 'PDF-Datei',
                'mapped' => false,
                'required' => $options['require_file'],
                'help' => $options['require_file']
                    ? 'PDF, maximal 25 MB.'
                    : 'Leer lassen, um die vorhandene Datei beizubehalten.',
                'constraints' => [
                    new File(
                        maxSize: '25M',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'Bitte eine PDF-Datei hochladen.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductDocument::class,
            'require_file' => false,
        ]);

        $resolver->setAllowedTypes('require_file', 'bool');
    }
}
