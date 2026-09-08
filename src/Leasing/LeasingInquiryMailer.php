<?php
declare(strict_types=1);
namespace App\Leasing;
use App\Entity\Leasing\LeasingInquiry; use Psr\Log\LoggerInterface; use Symfony\Component\Mailer\MailerInterface; use Symfony\Component\Mime\Email; use Symfony\Contracts\Translation\TranslatorInterface; use Twig\Environment;
final class LeasingInquiryMailer
{
 public function __construct(private MailerInterface $mailer,private Environment $twig,private LoggerInterface $logger,private TranslatorInterface $translator,private string $defaultRecipient){}
 public function send(LeasingInquiry $inquiry,?string $internalRecipient):void
 {
  try{$this->mailer->send((new Email())->from($this->defaultRecipient)->to($inquiry->getEmail())->subject($this->translator->trans('cardnext.leasing.email.customer_subject'))->html($this->twig->render('email/leasing_customer.html.twig',['inquiry'=>$inquiry])));$recipient=$internalRecipient?:$this->defaultRecipient;if($recipient!=='')$this->mailer->send((new Email())->from($this->defaultRecipient)->to($recipient)->subject($this->translator->trans('cardnext.leasing.email.internal_subject',['%number%'=>$inquiry->getInquiryNumber()]))->html($this->twig->render('email/leasing_internal.html.twig',['inquiry'=>$inquiry])));}catch(\Throwable $e){$this->logger->error('Leasing inquiry email could not be sent.',['inquiry'=>$inquiry->getInquiryNumber(),'exception'=>$e]);}
 }
}
