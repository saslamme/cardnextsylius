<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Content\ChannelHomepageContent;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

final class ChannelHomepageContentType extends AbstractType
{
    /** @param RepositoryInterface<LocaleInterface> $localeRepository */
    public function __construct(private readonly RepositoryInterface $localeRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('channel', ChannelChoiceType::class, ['label' => 'Verkaufskanal', 'required' => true])
            ->add('localeCode', ChoiceType::class, ['label' => 'Sprache', 'choices' => $this->localeChoices(), 'choice_translation_domain' => false])
        ;
        foreach (['hero', 'intro', 'why', 'cta'] as $prefix) {
            $builder->add($prefix . 'Kicker', TextType::class, ['label' => 'Kicker', 'required' => false])
                ->add($prefix . 'Title', TextType::class, ['label' => 'Überschrift', 'required' => false])
                ->add($prefix . 'Text', TextareaType::class, ['label' => 'Text', 'required' => false, 'attr' => ['rows' => 5]])
            ;
        }
        $builder
            ->add('heroImageFile', FileType::class, $this->imageOptions('Hero-Bild', 'Optionales individuelles Hero-Bild für diesen Verkaufskanal und diese Sprache. Ohne Upload wird das Standardbild verwendet. JPEG, PNG oder WebP, max. 5 MB.'))
            ->add('removeHeroImage', CheckboxType::class, ['label' => 'Hero-Bild entfernen', 'required' => false, 'help' => 'Ein neuer Upload hat Vorrang vor dieser Auswahl.'])
            ->add('introImageFile', FileType::class, $this->imageOptions('Bild', 'Optionales Bild für den Einleitungs-/Servicebereich. JPEG, PNG oder WebP, max. 5 MB.'))
            ->add('removeIntroImage', CheckboxType::class, ['label' => 'Einleitungsbild entfernen', 'required' => false, 'help' => 'Ein neuer Upload hat Vorrang vor dieser Auswahl.'])
            ->add('ctaImageFile', FileType::class, $this->imageOptions('Hintergrundbild', 'Optionales Hintergrundbild für den Beratungsbereich. JPEG, PNG oder WebP, max. 5 MB.'))
            ->add('removeCtaImage', CheckboxType::class, ['label' => 'CTA-Bild entfernen', 'required' => false, 'help' => 'Ein neuer Upload hat Vorrang vor dieser Auswahl.'])
        ;
        $builder->add('footerText', TextareaType::class, ['label' => 'Beschreibung', 'required' => false, 'attr' => ['rows' => 5]]);
    }

    /** @return array<string, mixed> */
    private function imageOptions(string $label, string $help): array
    {
        return ['label' => $label, 'required' => false, 'help' => $help, 'attr' => ['accept' => 'image/jpeg,image/png,image/webp'], 'constraints' => [new Image(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], mimeTypesMessage: 'Nur JPEG-, PNG- oder WebP-Bilder sind erlaubt.')]];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ChannelHomepageContent::class]);
    }

    /** @return array<string, string> */
    private function localeChoices(): array
    {
        $choices = [];
        foreach ($this->localeRepository->findAll() as $locale) {
            if ($locale->getCode() !== null && $locale->getName() !== null) {
                $choices[$locale->getName()] = $locale->getCode();
            }
        }

        return $choices;
    }
}
