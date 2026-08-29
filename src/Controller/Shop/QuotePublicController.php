<?php
declare(strict_types=1);
namespace App\Controller\Shop;
use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteRequestHistory;
use App\Enum\Quote\QuoteRequestStatus;
use App\Enum\Quote\QuoteStatus;
use App\Service\Quote\QuoteOfferMailer;
use App\Service\Quote\QuotePdfRenderer;
use App\Service\Quote\QuotePublicAccessTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/{_locale}/angebot/{number}/v{version}/{token}', requirements: ['version'=>'\\d+','token'=>'[a-fA-F0-9]{64}'])]
final class QuotePublicController extends AbstractController
{
 public function __construct(private EntityManagerInterface $em,private QuotePublicAccessTokenManager $tokens){}
 #[Route('',name:'cardnext_shop_quote_public',methods:['GET'])]
 public function show(string $number,int $version,string $token):Response
 {
  $quote=$this->quote($number,$version,$token); $first=$quote->recordViewed(new \DateTimeImmutable());
  if($first)$quote->getQuoteRequest()->addHistory(new QuoteRequestHistory('quote_first_viewed',null,null,'Angebot '.$quote->getNumber().' v'.$quote->getVersion().' erstmals geöffnet'));
  $this->em->flush(); return $this->secure($this->render('shop/quote/public.html.twig',['quote'=>$quote,'token'=>$token,'expired'=>$quote->isExpired()]));
 }
 #[Route('/pdf',name:'cardnext_shop_quote_public_pdf',methods:['GET'])]
 public function pdf(string $number,int $version,string $token,QuotePdfRenderer $renderer):Response
 {
  $q=$this->quote($number,$version,$token); $safe=preg_replace('/[^A-Za-z0-9._-]/','-',$q->getNumber())?:'Angebot';
  return $this->secure(new Response($renderer->render($q),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="Angebot-'.$safe.'-v'.$q->getVersion().'.pdf"']));
 }
 #[Route('/annehmen',name:'cardnext_shop_quote_accept',methods:['POST'])]
 public function accept(string $number,int $version,string $token,Request $request,QuoteOfferMailer $mailer,LoggerInterface $logger):Response
 { return $this->decide($number,$version,$token,$request,true,$mailer,$logger); }
 #[Route('/ablehnen',name:'cardnext_shop_quote_reject',methods:['POST'])]
 public function reject(string $number,int $version,string $token,Request $request,QuoteOfferMailer $mailer,LoggerInterface $logger):Response
 { return $this->decide($number,$version,$token,$request,false,$mailer,$logger); }
 private function decide(string $number,int $version,string $token,Request $request,bool $accept,QuoteOfferMailer $mailer,LoggerInterface $logger):Response
 {
  $q=$this->quote($number,$version,$token);
  if($q->getStatus()!==QuoteStatus::Sent || $q->isExpired()) return $this->secure(new Response('The quote cannot be decided.',Response::HTTP_CONFLICT));
  $csrf=$request->request->get('_token'); if(!is_string($csrf)||!$this->isCsrfTokenValid('quote_decide_'.$q->getId(),$csrf)) throw $this->createAccessDeniedException();
  $name=trim((string)$request->request->get('name')); $reason=$request->request->get('reason'); $reason=is_string($reason)?$reason:null;
  if($accept && $request->request->get('binding')!=='1') return $this->secure(new Response('Confirmation is required.',Response::HTTP_UNPROCESSABLE_ENTITY));
  try { if($accept){$q->accept($name,new \DateTimeImmutable());$requestStatus=$q->getQuoteRequest()->getStatus();if($requestStatus!==QuoteRequestStatus::Closed){if(!$requestStatus->canTransitionTo(QuoteRequestStatus::Closed))throw new \DomainException('Request cannot be closed.');$q->getQuoteRequest()->setStatus(QuoteRequestStatus::Closed);}}else{$q->reject($name,$reason,new \DateTimeImmutable());}
  } catch(\InvalidArgumentException|\DomainException){return $this->secure(new Response('Invalid decision.',Response::HTTP_UNPROCESSABLE_ENTITY));}
  $event=$accept?'quote_accepted':'quote_rejected'; $q->getQuoteRequest()->addHistory(new QuoteRequestHistory($event,null,null,'Angebot '.$q->getNumber().' v'.$q->getVersion().($accept?' angenommen':' abgelehnt')));
  $this->em->flush();
  $public=$this->generateUrl('cardnext_shop_quote_public',['_locale'=>$q->getLocaleCode(),'number'=>$q->getNumber(),'version'=>$q->getVersion(),'token'=>$token],UrlGeneratorInterface::ABSOLUTE_URL);
  $admin=$this->generateUrl('cardnext_admin_quote_show',['id'=>$q->getQuoteRequest()->getId()],UrlGeneratorInterface::ABSOLUTE_URL);
  try{$mailer->sendDecision($q,$public,$admin,$accept);}catch(\Throwable $e){$logger->error('Quote decision notification failed',['quoteId'=>$q->getId(),'exceptionClass'=>$e::class]);}
  return $this->secure($this->render('shop/quote/decision.html.twig',['quote'=>$q,'accepted'=>$accept]));
 }
 private function quote(string $number,int $version,string $token):Quote
 {
  $q=$this->em->getRepository(Quote::class)->findOneBy(['number'=>$number,'version'=>$version]);
  if(!$q instanceof Quote || !$this->tokens->isValid($q,$token))throw $this->createNotFoundException(); return $q;
 }
 private function secure(Response $response):Response{$response->headers->set('Cache-Control','private, no-store');$response->headers->set('X-Robots-Tag','noindex, nofollow, noarchive');$response->headers->set('Referrer-Policy','no-referrer');return $response;}
}
