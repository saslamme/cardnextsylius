<?php
declare(strict_types=1);
namespace App\Leasing;
use App\Entity\Leasing\LeasingInquiry; use App\Entity\Product\Product; use Doctrine\ORM\EntityManagerInterface; use Sylius\Component\Core\Model\ChannelInterface; use Symfony\Component\Validator\Validator\ValidatorInterface;
final class LeasingInquiryManager
{
 public function __construct(private EntityManagerInterface $em,private LeasingEligibilityChecker $checker,private ValidatorInterface $validator,private LeasingInquiryMailer $mailer){}
 public function create(Product $product,ChannelInterface $channel,int $duration,array $data):LeasingInquiry
 {
  $offer=$this->checker->offer($product,$channel); if($offer===null)throw new \DomainException('Product is not eligible for leasing.');
  $selected=null;foreach($offer->rates as $rate)if($rate['factor']->getDurationMonths()===$duration)$selected=$rate; if($selected===null)throw new \DomainException('Invalid leasing duration.');
  $q=new LeasingInquiry();$q->setProduct($product);$q->setProductVariant($offer->variant);$q->setChannel($channel);$q->setProductNameSnapshot((string)$product->getName());$q->setProductCodeSnapshot((string)$offer->variant->getCode());$q->setCurrencyCode($offer->currencyCode);$q->setNetPrice($offer->netPrice);$q->setLeasingFactorSnapshot($selected['factor']->getFactor());$q->setDurationMonths($duration);$q->setCalculatedMonthlyRate($selected['rate']);
  foreach(['company','firstName','lastName','email','phone','street','houseNumber','postalCode','city','countryCode','message'] as $field){$setter='set'.ucfirst($field);$value=trim((string)($data[$field]??''));$q->$setter(in_array($field,['company','firstName','lastName','email','postalCode','city'],true)?$value:($value!==''?$value:null));}
  $errors=$this->validator->validate($q);if(count($errors)>0)throw new \DomainException((string)$errors);
  $this->em->persist($q);$this->em->flush();$q->setInquiryNumber(sprintf('L-%s-%05d',date('Y'),$q->getId()));$this->em->flush();$this->mailer->send($q,$offer->configuration->getNotificationEmail());return $q;
 }
}
