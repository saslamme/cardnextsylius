<?php
declare(strict_types=1);
namespace App\Form\Type;
use App\Entity\Channel\Channel;
use App\Entity\Product\ProductBundleChannel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
final class ProductBundleChannelType extends AbstractType { public function buildForm(FormBuilderInterface $b, array $o): void { $b->add('channel', EntityType::class, ['class' => Channel::class, 'choice_label' => 'code'])->add('enabled', CheckboxType::class, ['required' => false])->add('discountType', ChoiceType::class, ['choices' => ['Kein Rabatt' => 'NONE', 'Fester Betrag' => 'FIXED', 'Prozent' => 'PERCENT']])->add('fixedDiscount', IntegerType::class, ['required' => false, 'help' => 'Minor Units, z. B. 8400 = 84,00 €'])->add('percentageDiscount', IntegerType::class, ['required' => false, 'help' => 'Basispunkte, z. B. 500 = 5,00 %']); } public function configureOptions(OptionsResolver $r): void { $r->setDefaults(['data_class' => ProductBundleChannel::class]); } }
