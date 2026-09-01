<?php

declare(strict_types=1);

namespace App\Form\Extension\Admin;

use App\Branding\ChannelBrandingUploader;
use App\Entity\Channel\Channel;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class ChannelTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly ChannelBrandingUploader $uploader)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('themeKey', TextType::class, ['required' => false, 'label' => 'Branding-Key', 'help' => 'Technischer Markenbezeichner, z. B. cardnext, identible oder inplastor. Unabhängig vom Sylius-Theme.'])
            ->add('brandName', TextType::class, ['required' => false, 'label' => 'Markenname'])
            ->add('logoFile', FileType::class, ['required' => false, 'label' => 'Logo', 'help' => 'PNG, WebP oder JPEG; maximal 2 MB.'])
            ->add('logoDarkFile', FileType::class, ['required' => false, 'label' => 'Logo dunkel / Footer-Logo'])
            ->add('faviconFile', FileType::class, ['required' => false, 'label' => 'Favicon'])
            ->add('primaryColor', TextType::class, $this->color('Primärfarbe'))
            ->add('primaryHoverColor', TextType::class, $this->color('Primärfarbe Hover'))
            ->add('primarySoftColor', TextType::class, $this->color('Primärfarbe hell'))
            ->add('inkColor', TextType::class, $this->color('Dunkelfarbe'))
            ->add('textColor', TextType::class, $this->color('Textfarbe'))
            ->add('footerColor', TextType::class, $this->color('Footerfarbe'));
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if ($data instanceof Channel && $event->getForm()->isValid()) {
                $this->uploader->upload($data);
            }
        });
    }

    /** @return array<string, mixed> */
    private function color(string $label): array
    {
        return ['required' => false, 'label' => $label, 'help' => 'Optionales Hex-Format (#RGB oder #RRGGBB); leer verwendet den Cardnext-Standard.', 'attr' => ['placeholder' => '#123456', 'pattern' => '^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$']];
    }

    public static function getExtendedTypes(): iterable
    {
        return [ChannelType::class];
    }
}
