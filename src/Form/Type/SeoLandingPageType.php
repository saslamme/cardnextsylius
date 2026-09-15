<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Channel\Channel;
use App\Entity\Seo\SeoLandingPage;
use App\Entity\Taxonomy\Taxon;
use App\Seo\SeoLandingPageFilterDefinition;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SeoLandingPageType extends AbstractType
{
    public function __construct(private readonly SeoLandingPageFilterDefinition $filterDefinition)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('internalName', TextType::class, ['label' => 'Interner Name'])
            ->add('enabled', CheckboxType::class, ['label' => 'Aktiv', 'required' => false])
            ->add('channel', EntityType::class, ['class' => Channel::class, 'choice_label' => 'name', 'label' => 'Verkaufskanal', 'attr' => ['data-action' => 'change->seo-filter-builder#load']])
            ->add('locale', TextType::class, ['label' => 'Locale', 'help' => 'Zum Beispiel de_DE', 'attr' => ['data-action' => 'change->seo-filter-builder#load']])
            ->add('path', TextType::class, ['label' => 'URL / Path', 'help' => 'Beginnt mit /; Query-Strings und Fragmente sind nicht erlaubt.'])
            ->add('h1', TextType::class, ['label' => 'H1'])
            ->add('metaTitle', TextType::class, ['label' => 'Meta Title'])
            ->add('metaDescription', TextareaType::class, ['label' => 'Meta Description', 'required' => false])
            ->add('topContent', TextareaType::class, ['label' => 'Text oben (Rich Text)', 'required' => false, 'attr' => ['rows' => 8]])
            ->add('bottomContent', TextareaType::class, ['label' => 'Text unten (Rich Text)', 'required' => false, 'attr' => ['rows' => 8]])
            ->add('robotsIndex', CheckboxType::class, ['label' => 'Index', 'required' => false])
            ->add('robotsFollow', CheckboxType::class, ['label' => 'Follow', 'required' => false])
            ->add('canonicalUrl', UrlType::class, ['label' => 'Canonical Override', 'required' => false, 'default_protocol' => 'https'])
            ->add('baseTaxon', EntityType::class, ['class' => Taxon::class, 'choice_label' => 'name', 'label' => 'Basistaxon', 'attr' => ['data-seo-filter-builder-target' => 'taxon', 'data-action' => 'change->seo-filter-builder#taxonChanged']])
            ->add('filterDefinition', HiddenType::class, ['attr' => ['data-seo-filter-builder-target' => 'definition']]);
        $builder->get('filterDefinition')->addModelTransformer(new CallbackTransformer(
            static fn (array $value): string => json_encode($value, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) ?: '{}',
            static function (?string $value): array { $decoded = json_decode((string) $value, true); return is_array($decoded) ? $decoded : []; },
        ));
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $page = $event->getData();
            if ($page instanceof SeoLandingPage) {
                $page->setFilterDefinition($this->filterDefinition->normalize($page->getFilterDefinition(), $page->getBaseTaxon(), $page->getLocale()));
            }
        });
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => SeoLandingPage::class]); }
}
