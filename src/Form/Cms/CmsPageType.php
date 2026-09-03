<?php

declare(strict_types=1);

namespace App\Form\Cms;

use App\Entity\Cms\CmsLayout;
use App\Entity\Cms\CmsPage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;

final class CmsPageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, ['label' => 'Interner Code'])
            ->add('layout', EntityType::class, ['class' => CmsLayout::class, 'label' => 'Layout', 'choice_label' => 'name'])
            ->add('channels', ChannelChoiceType::class, ['label' => 'Verkaufskanäle', 'multiple' => true, 'expanded' => true])
            ->add('status', ChoiceType::class, ['label' => 'Status', 'choices' => ['Entwurf' => CmsPage::STATUS_DRAFT, 'Veröffentlicht' => CmsPage::STATUS_PUBLISHED, 'Deaktiviert' => CmsPage::STATUS_DISABLED]])
            ->add('publishAt', DateTimeType::class, ['label' => 'Veröffentlichen ab', 'required' => false, 'widget' => 'single_text'])
            ->add('unpublishAt', DateTimeType::class, ['label' => 'Veröffentlichen bis', 'required' => false, 'widget' => 'single_text'])
            ->add('includeInSitemap', CheckboxType::class, ['label' => 'In Sitemap aufnehmen', 'required' => false])
            ->add('translations', CollectionType::class, ['entry_type' => CmsPageTranslationType::class, 'label' => false, 'by_reference' => false, 'allow_add' => false, 'allow_delete' => false]);
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $page = $event->getData();
            if (!$page instanceof CmsPage) {
                return;
            }
            foreach ($page->getTranslations()->toArray() as $translation) {
                if (trim($translation->getTitle()) === '' && trim($translation->getSlug()) === '') {
                    $page->removeTranslation($translation);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CmsPage::class]);
    }
}
