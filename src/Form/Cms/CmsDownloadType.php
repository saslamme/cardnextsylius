<?php

declare(strict_types=1);

namespace App\Form\Cms;

use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsDownload;
use Sylius\Bundle\AdminBundle\Form\Type\ProductAutocompleteType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class CmsDownloadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, ['label' => 'Interner Code'])
            ->add('type', ChoiceType::class, ['label' => 'Typ', 'choices' => array_combine(['Handbuch', 'Datenblatt', 'Treiber', 'Software', 'Firmware', 'Zertifikat', 'Sonstiges'], CmsDownload::TYPES)])
            ->add('manufacturer', TextType::class, ['label' => 'Hersteller'])
            ->add('productFamily', TextType::class, ['label' => 'Produktfamilie', 'required' => false])
            ->add('version', TextType::class, ['label' => 'Version', 'required' => false])
            ->add('operatingSystems', ChoiceType::class, ['label' => 'Betriebssysteme', 'required' => false, 'multiple' => true, 'expanded' => true, 'choices' => array_combine(['Windows 11', 'Windows 10', 'Windows Server', 'macOS', 'Linux', 'Sonstiges'], CmsDownload::OPERATING_SYSTEMS)])
            ->add('position', IntegerType::class, ['label' => 'Position'])
            ->add('enabled', CheckboxType::class, ['label' => 'Aktiv', 'required' => false])
            ->add('publishedAt', DateTimeType::class, ['label' => 'Veröffentlichen ab', 'required' => false, 'widget' => 'single_text'])
            ->add('channels', EntityType::class, ['class' => Channel::class, 'label' => 'Verkaufskanäle', 'multiple' => true, 'expanded' => true, 'choice_label' => static fn (Channel $channel): string => sprintf('%s (%s)', $channel->getName() ?? $channel->getCode(), $channel->getCode())])
            ->add('products', ProductAutocompleteType::class, ['label' => 'Produkte', 'multiple' => true, 'required' => false, 'help' => 'Nach Produktname oder Artikelnummer suchen.'])
            ->add('uploadedFile', FileType::class, ['label' => 'Neue Datei', 'mapped' => false, 'required' => false, 'constraints' => [new File(maxSize: '50M', extensions: ['pdf', 'zip', 'txt', 'csv', 'xml'])]])
            ->add('externalUrl', TextType::class, ['label' => 'Externe HTTPS-URL', 'required' => false])
            ->add('translations', CollectionType::class, ['entry_type' => CmsDownloadTranslationType::class, 'label' => false, 'by_reference' => false, 'allow_add' => false, 'allow_delete' => false]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $download = $event->getData();
            if (!$download instanceof CmsDownload) {
                return;
            }
            foreach ($download->getTranslations()->toArray() as $translation) {
                if (trim($translation->getTitle()) === '') {
                    $download->removeTranslation($translation);
                }
            }
            if ($event->getForm()->get('uploadedFile')->getData() !== null) {
                $download->setExternalUrl(null);
                if ($download->getFilePath() === null) {
                    // Makes entity source validation possible before the validated upload is stored.
                    $download->setFilePath('__pending_upload__');
                }
            } elseif ($download->getExternalUrl() !== null) {
                $download->setFilePath(null);
                $download->setOriginalFilename(null);
                $download->setMimeType(null);
                $download->setFileSize(null);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CmsDownload::class]);
    }
}
