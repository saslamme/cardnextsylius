<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Email\ChannelEmailBrandingResolver;
use App\Entity\Channel\Channel;
use App\Entity\Quote\Quote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final class QuoteOfferMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $recipient,
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
        private ChannelEmailBrandingResolver $brandingResolver,
    ) {
    }

    public function send(Quote $quote, string $accountUrl): void
    {
        $branding = $this->branding($quote);
        $mail = (new TemplatedEmail())
            ->from($branding === null ? $this->recipient : new Address($branding->senderAddress, $branding->senderName))
            ->to($quote->getCustomerEmail())
            ->locale($quote->getLocaleCode())
            ->subject($this->translator->trans('quote_mail.offer_subject', ['%number%' => $quote->getNumber(), '%brand%' => $branding->brandName ?? 'Cardnext'], locale: $quote->getLocaleCode()))
            ->htmlTemplate('email/quote_offer.html.twig')
            ->textTemplate('email/quote_offer.txt.twig')
            ->context([
                'quote' => $quote,
                'accountUrl' => $accountUrl,
                'emailBranding' => $branding,
            ]);
        if ($branding?->replyToAddress !== null) {
            $mail->replyTo(new Address($branding->replyToAddress));
        }

        $this->mailer->send($mail);
    }

    public function sendDecision(Quote $quote, string $accountUrl, string $adminUrl, bool $accepted): void
    {
        $kind = $accepted ? 'accepted' : 'rejected';
        $branding = $this->branding($quote);
        $from = $branding === null ? $this->recipient : new Address($branding->senderAddress, $branding->senderName);
        $customer = (new TemplatedEmail())->from($from)->to($quote->getCustomerEmail())->locale($quote->getLocaleCode())->subject($this->translator->trans('quote_mail.' . $kind . '_subject', locale: $quote->getLocaleCode()))->htmlTemplate('email/quote_' . $kind . '_customer.html.twig')->context(['quote' => $quote, 'accountUrl' => $accountUrl, 'emailBranding' => $branding]);
        if ($branding?->replyToAddress !== null) {
            $customer->replyTo(new Address($branding->replyToAddress));
        }
        $internal = (new TemplatedEmail())->from($this->recipient)->to($this->recipient)->subject(sprintf('Angebot %s v%d wurde %s.', $quote->getNumber(), $quote->getVersion(), $accepted ? 'angenommen' : 'abgelehnt'))->htmlTemplate('email/quote_' . $kind . '_internal.html.twig')->context(['quote' => $quote, 'adminUrl' => $adminUrl]);
        $this->mailer->send($customer);
        $this->mailer->send($internal);
    }

    private function branding(Quote $quote): ?\App\Email\ChannelEmailBranding
    {
        $channel = $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => $quote->getChannelCode()]);

        return $channel instanceof Channel ? $this->brandingResolver->resolve($channel) : null;
    }
}
