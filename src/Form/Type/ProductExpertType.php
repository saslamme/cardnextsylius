<?php
declare(strict_types=1);
namespace App\Form\Type;
use App\Entity\Channel\Channel;
use App\Entity\Sales\ProductExpert;
use App\Entity\User\AdminUser;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
final class ProductExpertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('adminUser', EntityType::class, ['class' => AdminUser::class, 'choice_label' => 'email'])
            ->add('channel', EntityType::class, ['class' => Channel::class, 'choice_label' => 'name'])
            ->add('displayName', null, ['label' => 'Anzeigename'])->add('slug')
            ->add('introText', TextareaType::class, ['required' => false, 'label' => 'Introtext'])
            ->add('enabled', CheckboxType::class, ['required' => false, 'label' => 'Aktiv']);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => ProductExpert::class]); }
}
