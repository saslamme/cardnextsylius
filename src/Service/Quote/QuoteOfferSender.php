<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Customer\Customer;
use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteRequestHistory;
use App\Entity\User\ShopUser;
use App\Enum\Quote\QuoteStatus;
use Doctrine\ORM\EntityManagerInterface;

final class QuoteOfferSender
{
    public function __construct(private EntityManagerInterface $em, private QuoteOfferMailer $mailer, private QuoteAccountUrlGenerator $accountUrls)
    {
    }

    public function send(Quote $quote): void
    {
        if (!in_array($quote->getStatus(), [QuoteStatus::Ready, QuoteStatus::Sent], true)) {
            throw new \DomainException('Dieses Angebot kann nicht versendet werden.');
        }
        if ($quote->isExpired()) {
            throw new \DomainException('Ein abgelaufenes Angebot kann nicht versendet werden.');
        }
        $customer = $quote->getCustomer();
        if (!$customer instanceof Customer) {
            $matches = $this->em->createQuery('SELECT c FROM App\\Entity\\Customer\\Customer c WHERE LOWER(c.email) = :email')->setParameter('email', mb_strtolower(trim($quote->getCustomerEmail())))->getResult();
            // @phpstan-ignore offsetAccess.nonOffsetAccessible, argument.type
            if (count($matches) === 1 && $matches[0] instanceof Customer) {
                $customer = $matches[0];
                $quote->setCustomer($customer);
            }
        }
        $user = $customer?->getUser();
        if (!$user instanceof ShopUser || !$user->isEnabled()) {
            throw new \DomainException('Das Angebot kann nicht versendet werden. Für diesen Kunden existiert noch kein aktives Kundenkonto.');
        }
        $this->mailer->send($quote, $this->accountUrls->view($quote));
        $resent = $quote->getStatus() === QuoteStatus::Sent;
        $quote->recordSent(new \DateTimeImmutable());
        $quote->getQuoteRequest()->addHistory(new QuoteRequestHistory($resent ? 'quote_resent' : 'quote_sent', null, null, 'Angebot ' . $quote->getNumber() . ' v' . $quote->getVersion() . ($resent ? ' erneut versendet' : ' versendet')));
        $this->em->flush();
    }
}
