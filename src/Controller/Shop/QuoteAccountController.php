<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer\Customer;
use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteRequestHistory;
use App\Entity\User\ShopUser;
use App\Enum\Quote\QuoteRequestStatus;
use App\Enum\Quote\QuoteStatus;
use App\Service\Quote\QuoteAccountUrlGenerator;
use App\Service\Quote\QuoteOfferMailer;
use App\Service\Quote\QuotePdfRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/{_locale}/account/angebote', priority: 200)]
final class QuoteAccountController extends AbstractController
{
    private const STATUSES = [QuoteStatus::Sent, QuoteStatus::Accepted, QuoteStatus::Rejected, QuoteStatus::Superseded];

    public function __construct(private EntityManagerInterface $em, private ChannelContextInterface $channels)
    {
    }

    #[Route('', name:'cardnext_shop_account_quote_index', methods:['GET'])]
    public function index(): Response
    {
        $customer = $this->customer();
        $quotes = $this->em->getRepository(Quote::class)->findBy(['customer' => $customer, 'channelCode' => $this->channelCode(), 'status' => self::STATUSES], ['quoteDate' => 'DESC', 'version' => 'DESC']);

        return $this->secure($this->render('shop/account/quote/index.html.twig', ['quotes' => $quotes]));
    }

    #[Route('/{number}/v{version}', name:'cardnext_shop_account_quote_show', requirements:['version' => '\d+'], methods:['GET'])]
    public function show(string $number, int $version): Response
    {
        $quote = $this->quote($number, $version);
        $first = $quote->recordViewed(new \DateTimeImmutable());
        if ($first) {
            $quote->getQuoteRequest()->addHistory(new QuoteRequestHistory('quote_first_viewed', null, null, 'Angebot ' . $quote->getNumber() . ' v' . $quote->getVersion() . ' erstmals im Kundenkonto geöffnet'));
        }
        $this->em->flush();

        return $this->secure($this->render('shop/account/quote/show.html.twig', ['quote' => $quote, 'expired' => $quote->isExpired()]));
    }

    #[Route('/{number}/v{version}/pdf', name:'cardnext_shop_account_quote_pdf', requirements:['version' => '\d+'], methods:['GET'])]
    public function pdf(string $number, int $version, QuotePdfRenderer $renderer): Response
    {
        $q = $this->quote($number, $version);
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '-', $q->getNumber()) ?: 'Angebot';

        return $this->secure(new Response($renderer->render($q), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Angebot-' . $safe . '-v' . $q->getVersion() . '.pdf"']));
    }

    #[Route('/{number}/v{version}/annehmen', name:'cardnext_shop_account_quote_accept', requirements:['version' => '\d+'], methods:['POST'])]
    public function accept(string $number, int $version, Request $request, QuoteOfferMailer $mailer, QuoteAccountUrlGenerator $urls, LoggerInterface $logger): Response
    {
        return $this->decide($number, $version, $request, true, $mailer, $urls, $logger);
    }

    #[Route('/{number}/v{version}/ablehnen', name:'cardnext_shop_account_quote_reject', requirements:['version' => '\d+'], methods:['POST'])]
    public function reject(string $number, int $version, Request $request, QuoteOfferMailer $mailer, QuoteAccountUrlGenerator $urls, LoggerInterface $logger): Response
    {
        return $this->decide($number, $version, $request, false, $mailer, $urls, $logger);
    }

    private function decide(string $number, int $version, Request $request, bool $accept, QuoteOfferMailer $mailer, QuoteAccountUrlGenerator $urls, LoggerInterface $logger): Response
    {
        $customer = $this->customer();
        $q = $this->quote($number, $version);
        if ($q->getStatus() !== QuoteStatus::Sent || $q->isExpired()) {
            $this->addFlash('error', 'Dieses Angebot kann nicht mehr entschieden werden.');

            return $this->redirectToRoute('cardnext_shop_account_quote_show', ['number' => $number, 'version' => $version]);
        }
        if (!$this->isCsrfTokenValid('quote_decide_' . $q->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($accept && $request->request->get('binding') !== '1') {
            $this->addFlash('error', 'Bitte bestätigen Sie die verbindliche Annahme.');

            return $this->redirectToRoute('cardnext_shop_account_quote_show', ['number' => $number, 'version' => $version]);
        }
        $name = trim((string) $customer->getFullName());
        if ($name === '') {
            $name = $customer->getEmail() ?? 'Kunde';
        }
        if ($accept) {
            $q->accept($name, new \DateTimeImmutable());
            $state = $q->getQuoteRequest()->getStatus();
            if ($state !== QuoteRequestStatus::Closed && $state->canTransitionTo(QuoteRequestStatus::Closed)) {
                $q->getQuoteRequest()->setStatus(QuoteRequestStatus::Closed);
            }
        } else {
            $reason = $request->request->get('reason');
            $q->reject($name, is_string($reason) ? $reason : null, new \DateTimeImmutable());
        }
        $q->getQuoteRequest()->addHistory(new QuoteRequestHistory($accept ? 'quote_accepted' : 'quote_rejected', null, null, 'Angebot ' . $q->getNumber() . ' v' . $q->getVersion() . ($accept ? ' angenommen' : ' abgelehnt')));
        $this->em->flush();

        try {
            $mailer->sendDecision($q, $urls->view($q), $this->generateUrl('cardnext_admin_quote_show', ['id' => $q->getQuoteRequest()->getId()], UrlGeneratorInterface::ABSOLUTE_URL), $accept);
        } catch(\Throwable $e) {
            $logger->error('Quote decision notification failed', ['quoteId' => $q->getId(), 'exceptionClass' => $e::class]);
        }
        $this->addFlash('success', $accept ? 'Das Angebot wurde angenommen.' : 'Das Angebot wurde abgelehnt.');

        return $this->redirectToRoute('cardnext_shop_account_quote_show', ['number' => $number, 'version' => $version]);
    }

    private function customer(): Customer
    {
        $user = $this->getUser();
        if (!$user instanceof ShopUser || !$user->getCustomer() instanceof Customer) {
            throw $this->createAccessDeniedException();
        }

        return $user->getCustomer();
    }

    private function channelCode(): string
    {
        return (string) $this->channels->getChannel()->getCode();
    }

    private function quote(string $number, int $version): Quote
    {
        $q = $this->em->getRepository(Quote::class)->findOneBy(['number' => $number, 'version' => $version, 'customer' => $this->customer(), 'channelCode' => $this->channelCode(), 'status' => self::STATUSES]);
        if (!$q instanceof Quote) {
            throw $this->createNotFoundException();
        }

        return $q;
    }

    private function secure(Response $response): Response
    {
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
