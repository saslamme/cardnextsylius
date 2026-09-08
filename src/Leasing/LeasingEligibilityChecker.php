<?php
declare(strict_types=1);
namespace App\Leasing;
use App\Entity\Leasing\{LeasingConfiguration,LeasingFactor}; use App\Entity\Product\{Product,ProductVariant}; use Doctrine\ORM\EntityManagerInterface; use Sylius\Component\Channel\Context\ChannelContextInterface; use Sylius\Component\Core\Model\ChannelInterface;
final class LeasingEligibilityChecker
{
 public function __construct(private EntityManagerInterface $em,private ChannelContextInterface $channelContext,private LeasingCalculator $calculator){}
 public function offer(Product $product,?ChannelInterface $channel=null):?LeasingOffer
 {
  $config=$this->em->find(LeasingConfiguration::class,1); if(!$config?->isEnabled()||!$product->isLeasingEnabled())return null;
  $channel??=$this->channelContext->getChannel(); $variant=null;$price=null;
  foreach($product->getEnabledVariants() as $candidate){foreach($candidate->getChannelPricings() as $pricing){if($pricing->getChannelCode()===$channel->getCode()&&$pricing->getPrice()!==null){$variant=$candidate;$price=$pricing->getPrice();break 2;}}}
  if(!$variant instanceof ProductVariant||$price===null)return null; $price=$product->getLeasingCustomPrice()??$price; if($price<$config->getMinimumNetAmount())return null;
  $allowed=$product->getLeasingFactors()->toArray(); $factors=$allowed===[]?$this->em->getRepository(LeasingFactor::class)->findBy(['active'=>true],['position'=>'ASC','durationMonths'=>'ASC']):array_values(array_filter($allowed,fn(LeasingFactor $f)=>$f->isActive()));
  if($factors===[])return null; $rates=[];$preferred=0;$preferredFound=false;
  foreach($factors as $i=>$factor){$rates[]=['factor'=>$factor,'rate'=>$this->calculator->calculate($price,$factor->getFactor())];if($config->getDefaultFactor()?->getId()===$factor->getId()){$preferred=$i;$preferredFound=true;}}
  if($config->getDefaultFactor()!==null&&!$preferredFound){$values=array_column($rates,'rate');$preferred=(int)array_search(min($values),$values,true);}
  return new LeasingOffer($variant,$price,(string)$channel->getBaseCurrency()?->getCode(),$config,$rates,$preferred);
 }
}
