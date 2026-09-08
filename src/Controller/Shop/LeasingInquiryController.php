<?php
declare(strict_types=1);
namespace App\Controller\Shop;
use App\Entity\Product\Product; use App\Leasing\{LeasingEligibilityChecker,LeasingInquiryManager}; use Sylius\Component\Channel\Context\ChannelContextInterface; use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; use Symfony\Component\HttpFoundation\{Request,Response}; use Symfony\Component\Routing\Attribute\Route;
final class LeasingInquiryController extends AbstractController
{
 #[Route('/leasing/anfrage/{code}',name:'cardnext_shop_leasing_inquiry',methods:['GET','POST'])]
 public function __invoke(string $code,Request $r,\Doctrine\ORM\EntityManagerInterface $em,ChannelContextInterface $channels,LeasingEligibilityChecker $checker,LeasingInquiryManager $manager):Response
 { $product=$em->getRepository(Product::class)->findOneBy(['code'=>$code]);if(!$product instanceof Product||($offer=$checker->offer($product))===null)throw $this->createNotFoundException();
  if($r->isMethod('POST')){if(!$this->isCsrfTokenValid('leasing-'.$product->getCode(),(string)$r->request->get('_token')))throw $this->createAccessDeniedException();$data=$r->request->all();if(!isset($data['consent'])){$this->addFlash('error','cardnext.leasing.consent_required');}else{try{$inquiry=$manager->create($product,$channels->getChannel(),(int)($data['duration']??0),$data);$this->addFlash('success','cardnext.leasing.inquiry_success');return $this->redirectToRoute('cardnext_shop_leasing_inquiry',['code'=>$code]);}catch(\DomainException $e){$this->addFlash('error','cardnext.leasing.inquiry_invalid');}}}
  return $this->render('shop/leasing/inquiry.html.twig',['product'=>$product,'offer'=>$offer]); }
}
