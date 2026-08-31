<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Quote\Quote;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class QuoteOfferMailer
{
    private const LOGO_CID = 'cardnext-logo.svg';

    public function __construct(
        private MailerInterface $mailer,
        private string $recipient,
        private string $projectDir,
    ) {
    }

    public function send(Quote $quote, string $accountUrl): void
    {
        $mail = (new TemplatedEmail())
            ->from($this->recipient)
            ->to($quote->getCustomerEmail())
            ->locale($quote->getLocaleCode())
            ->subject((str_starts_with($quote->getLocaleCode(), 'en') ? 'Your quote ' : 'Ihr Angebot ') . $quote->getNumber() . ' von Cardnext')
            ->htmlTemplate('email/quote_offer.html.twig')
            ->textTemplate('email/quote_offer.txt.twig')
            ->context([
                'quote' => $quote,
                'accountUrl' => $accountUrl,
                'logoCid' => self::LOGO_CID,
            ])
            ->embedFromPath($this->projectDir . '/public/cardnext/cardnext.svg', self::LOGO_CID, 'image/svg+xml');

        $this->mailer->send($mail);
    }

    public function sendDecision(Quote $quote, string $accountUrl, string $adminUrl, bool $accepted): void
    {
        $kind = $accepted ? 'accepted' : 'rejected';
        $customer = (new TemplatedEmail())->from($this->recipient)->to($quote->getCustomerEmail())->locale($quote->getLocaleCode())->subject($accepted ? 'Bestätigung Ihrer Angebotsannahme' : 'Bestätigung Ihrer Angebotsablehnung')->htmlTemplate('email/quote_' . $kind . '_customer.html.twig')->context(['quote' => $quote, 'accountUrl' => $accountUrl]);
        $internal = (new TemplatedEmail())->from($this->recipient)->to($this->recipient)->subject(sprintf('Angebot %s v%d wurde %s.', $quote->getNumber(), $quote->getVersion(), $accepted ? 'angenommen' : 'abgelehnt'))->htmlTemplate('email/quote_' . $kind . '_internal.html.twig')->context(['quote' => $quote, 'adminUrl' => $adminUrl]);
        $this->mailer->send($customer);
        $this->mailer->send($internal);
    }
}
