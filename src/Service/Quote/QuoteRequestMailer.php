<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Email\ChannelEmailBrandingResolver;
use App\Entity\Channel\Channel;
use App\Entity\Quote\QuoteRequest;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class QuoteRequestMailer
{
    public function __construct(private MailerInterface $mailer, private LoggerInterface $logger, private UrlGeneratorInterface $router, private TranslatorInterface $translator, private string $recipient, private EntityManagerInterface $entityManager, private ChannelEmailBrandingResolver $brandingResolver)
    {
    }

    public function send(QuoteRequest $quote): void
    {
        try {
            $locale = $quote->getLocaleCode();
            $channel = $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => $quote->getChannelCode()]);
            $branding = $channel instanceof Channel ? $this->brandingResolver->resolve($channel) : null;
            $from = $branding === null ? $this->recipient : new Address($branding->senderAddress, $branding->senderName);
            $email = (new TemplatedEmail())->from($from)->to($quote->getEmail())->locale($locale)->subject($this->translator->trans('quote_mail.request_subject', ['%number%' => $quote->getNumber(), '%brand%' => $branding->brandName ?? 'Cardnext'], locale: $locale))->htmlTemplate('email/quote_customer.html.twig')->context(['quote' => $quote, 'emailBranding' => $branding]);
            if ($branding?->replyToAddress !== null) {
                $email->replyTo(new Address($branding->replyToAddress));
            }
            $this->mailer->send($email);
            $this->mailer->send((new TemplatedEmail())->from($this->recipient)->to($this->recipient)->subject('Neue Angebotsanfrage ' . $quote->getNumber())->htmlTemplate('email/quote_internal.html.twig')->context(['quote' => $quote, 'adminUrl' => $this->router->generate('cardnext_admin_quote_show', ['id' => $quote->getId()], UrlGeneratorInterface::ABSOLUTE_URL)]));
        } catch(\Throwable $e) {
            $this->logger->error('Quote request mail failed', ['quoteNumber' => $quote->getNumber(), 'exception' => $e]);
        }
    }
}
