<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Channel\Channel;
use App\Entity\Content\LegalPage;
use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\LocaleBundle\Form\Type\LocaleChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class LegalPageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Typ (interner Code)',
                'help' => 'Zum Beispiel imprint, privacy oder terms.',
            ])
            ->add('localeCode', LocaleChoiceType::class, [
                'label' => 'Sprache',
                'multiple' => false,
            ])
            ->add('channels', EntityType::class, [
                'class' => Channel::class,
                'label' => 'Verkaufskanäle',
                'choice_label' => static fn (Channel $channel): string => $channel->getName() ?? $channel->getCode() ?? '',
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('channel')
                    ->andWhere('channel.enabled = :enabled')
                    ->setParameter('enabled', true)
                    ->orderBy('channel.name', 'ASC'),
                'multiple' => true,
                'expanded' => true,
                'required' => true,
            ])
            ->add('title', TextType::class, [
                'label' => 'Seitentitel',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Inhalt',
                'help' => 'HTML ist erlaubt. Überschriften mit <h2>, Absätze mit <p>, Listen mit <ul>/<ol>.',
                'attr' => [
                    'rows' => 34,
                    'spellcheck' => 'true',
                    'style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13px;',
                ],
            ])
            ->add('metaTitle', TextType::class, [
                'label' => 'SEO-Titel',
                'required' => false,
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'SEO-Beschreibung',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 500],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LegalPage::class]);
    }
}
