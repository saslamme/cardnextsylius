<?php

declare(strict_types=1);

namespace App\Form\Cms;

use App\Entity\Cms\CmsPageTranslation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CmsPageTranslationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('locale', HiddenType::class)
            ->add('title', TextType::class, ['label' => 'Titel'])
            ->add('slug', TextType::class, ['label' => 'Slug', 'help' => 'Pfad ohne führenden Schrägstrich.'])
            ->add('lead', TextareaType::class, ['label' => 'Einleitung', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('metaTitle', TextType::class, ['label' => 'Meta-Titel', 'required' => false])
            ->add('metaDescription', TextareaType::class, ['label' => 'Meta-Beschreibung', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('canonicalUrl', TextType::class, ['label' => 'Canonical URL (HTTPS)', 'required' => false])
            ->add('robotsIndex', CheckboxType::class, ['label' => 'Suchmaschinen-Indexierung', 'required' => false])
            ->add('robotsFollow', CheckboxType::class, ['label' => 'Links folgen', 'required' => false])
            ->add('ogTitle', TextType::class, ['label' => 'Open-Graph-Titel', 'required' => false])
            ->add('ogDescription', TextareaType::class, ['label' => 'Open-Graph-Beschreibung', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CmsPageTranslation::class]);
    }
}
