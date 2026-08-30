<?php
declare(strict_types=1);
namespace App\Service\Quote;
use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteRequestHistory;
use App\Enum\Quote\QuoteStatus;
use Doctrine\ORM\EntityManagerInterface;
final class QuoteOfferSender
{
 public function __construct(private EntityManagerInterface $em,private QuotePublicAccessTokenManager $tokens,private QuotePdfRenderer $pdf,private QuoteOfferMailer $mailer,private QuotePublicUrlGenerator $publicUrls){}
 public function send(Quote $quote): void
 {
  if(!in_array($quote->getStatus(),[QuoteStatus::Ready,QuoteStatus::Sent],true)) throw new \DomainException('Dieses Angebot kann nicht versendet werden.');
  if($quote->isExpired()) throw new \DomainException('Ein abgelaufenes Angebot kann nicht versendet werden.');
  $oldHash=$quote->getAccessTokenHash(); $oldIssued=$quote->getAccessTokenIssuedAt(); $token=$this->tokens->issue($quote);
  $url=$this->publicUrls->view($quote,$token);
  try{$bytes=$this->pdf->render($quote);$this->mailer->send($quote,$url,$bytes);}catch(\Throwable $e){$quote->setPublicAccess($oldHash,$oldIssued);throw $e;}finally{unset($token,$url);}
  $resent=$quote->getStatus()===QuoteStatus::Sent; $quote->recordSent(new \DateTimeImmutable());
  $quote->getQuoteRequest()->addHistory(new QuoteRequestHistory($resent?'quote_resent':'quote_sent',null,null,'Angebot '.$quote->getNumber().' v'.$quote->getVersion().($resent?' erneut versendet':' versendet')));
  $this->em->flush();
 }
}
