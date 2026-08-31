<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteItem;
use App\Entity\Quote\QuoteRequest;
use App\Entity\Quote\QuoteRequestHistory;
use App\Entity\User\AdminUser;
use App\Enum\Quote\QuoteItemType;
use App\Enum\Quote\QuoteRequestStatus;
use App\Enum\Quote\QuoteStatus;
use App\Service\Quote\MinorUnitParser;
use App\Service\Quote\QuoteCalculator;
use App\Service\Quote\QuoteDraftGuard;
use App\Service\Quote\QuoteFactory;
use App\Service\Quote\QuoteOfferSender;
use App\Service\Quote\QuoteOrderConverter;
use App\Service\Quote\QuoteOrderDataValidator;
use App\Service\Quote\QuotePdfRenderer;
use App\Service\Quote\QuoteRevisionFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/cardnext/quotes', name: 'cardnext_admin_quote_')]
final class QuoteAdminController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager, private QuoteDraftGuard $draftGuard)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->adminOnly();
        $builder = $this->entityManager->getRepository(QuoteRequest::class)->createQueryBuilder('q')->orderBy('q.createdAt', 'DESC');
        if ($status = $request->query->get('status')) {
            $builder->andWhere('q.status = :status')->setParameter('status', $status);
        }
        if ($channel = $request->query->get('channel')) {
            $builder->andWhere('q.channelCode = :channel')->setParameter('channel', $channel);
        }

        return $this->render('admin/cardnext/quote/index.html.twig', ['quotes' => $builder->getQuery()->getResult(), 'statuses' => QuoteRequestStatus::cases()]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(QuoteRequest $quote): Response
    {
        $this->adminOnly();

        return $this->render('admin/cardnext/quote/show.html.twig', ['quote' => $quote, 'statuses' => QuoteRequestStatus::cases()]);
    }

    #[Route('/{id}/offer', name: 'create', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function create(QuoteRequest $request, Request $httpRequest, QuoteFactory $factory): Response
    {
        $this->adminOnly();
        $this->checkCsrf('quote_create_' . $request->getId(), $httpRequest);
        $quote = $factory->createFromRequest($request, $this->admin());

        return $this->redirectToRoute('cardnext_admin_quote_edit', ['id' => $quote->getId()]);
    }

    #[Route('/offer/{id}', name: 'edit', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function edit(Quote $quote, MinorUnitParser $money): Response
    {
        $this->adminOnly();

        return $this->render('admin/cardnext/quote/edit.html.twig', ['quote' => $quote, 'money' => $money]);
    }

    #[Route('/offer/{id}', name: 'update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(Quote $quote, Request $request, MinorUnitParser $money, QuoteCalculator $calculator): Response
    {
        $this->adminOnly();
        $this->checkCsrf('quote_edit_' . $quote->getId(), $request);

        try {
            $this->draftGuard->assertDraft($quote);
            $email = trim((string) $request->request->get('customerEmail', ''));
            if ($email !== '' && !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Bitte geben Sie eine gültige Kunden-E-Mail-Adresse ein.');
            }
            $countryCode = strtoupper(trim((string) $request->request->get('customerCountryCode', '')));
            if ($countryCode !== '' && !Countries::exists($countryCode)) {
                throw new \InvalidArgumentException('Bitte geben Sie das Land als gültigen ISO-2-Code ein.');
            }
            $quote->setCustomerCompany(trim((string) $request->request->get('customerCompany', '')));
            $quote->setCustomerContactName(trim((string) $request->request->get('customerContactName', '')));
            $quote->setCustomerEmail($email);
            $quote->setCustomerPhone($this->nullable($request->request->get('customerPhone')));
            $quote->setCustomerStreet($this->nullable($request->request->get('customerStreet')));
            $quote->setCustomerHouseNumber($this->nullable($request->request->get('customerHouseNumber')));
            $quote->setCustomerPostalCode($this->nullable($request->request->get('customerPostalCode')));
            $quote->setCustomerCity($this->nullable($request->request->get('customerCity')));
            $quote->setCustomerCountryCode($countryCode === '' ? null : $countryCode);
            foreach ($request->request->all('items') as $id => $values) {
                if (!is_array($values)) {
                    throw new \InvalidArgumentException('Ungültige Positionsdaten.');
                }
                $item = $this->itemOf($quote, (int) $id);
                $position = $values['position'] ?? null;
                $quantity = $values['quantity'] ?? null;
                $unitPrice = $values['unitPrice'] ?? null;
                if (!is_scalar($position) || !is_scalar($quantity) || !is_scalar($unitPrice)) {
                    throw new \InvalidArgumentException('Ungültige Positionsdaten.');
                }
                $item->setPosition(max(1, (int) $position));
                $item->setQuantity((int) $quantity);
                $item->setUnitPrice($money->parse((string) $unitPrice));
                $taxRate = $values['taxRate'] ?? '';
                $item->setTaxRate(is_scalar($taxRate) && trim((string) $taxRate) !== '' ? $money->parse((string) $taxRate) : null);
            }
            $quote->setShippingTotal($money->parse((string) $request->request->get('shippingTotal', '0')));
            $quote->setServiceTotal($money->parse((string) $request->request->get('serviceTotal', '0')));
            $quote->setValidUntil(($date = $request->request->get('validUntil')) ? new \DateTimeImmutable((string) $date) : null);
            $quote->setDeliveryTerms($this->nullable($request->request->get('deliveryTerms')));
            $quote->setPaymentTerms($this->nullable($request->request->get('paymentTerms')));
            $quote->setCustomerNote($this->nullable($request->request->get('customerNote')));
            $quote->setInternalNote($this->nullable($request->request->get('internalNote')));
            $quote->setDefaultTaxRate($money->parse((string) $request->request->get('defaultTaxRate', '0')));
            $quote->setTaxNote($this->nullable($request->request->get('taxNote')));
            $quote->setUpdatedBy($this->admin());
            $calculator->calculate($quote);
            $quote->getQuoteRequest()->addHistory(new QuoteRequestHistory('quote_updated', null, null, 'Angebot aktualisiert'));
            $this->entityManager->flush();
            $this->addFlash('success', 'Angebot gespeichert.');
        } catch (\InvalidArgumentException|\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('cardnext_admin_quote_edit', ['id' => $quote->getId()]);
    }

    #[Route('/offer/{id}/item', name: 'item_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function addItem(Quote $quote, Request $request, MinorUnitParser $money, QuoteCalculator $calculator): Response
    {
        $this->adminOnly();
        $this->checkCsrf('quote_edit_' . $quote->getId(), $request);

        try {
            $this->draftGuard->assertDraft($quote);
            $item = new QuoteItem();
            $item->setName(trim((string) $request->request->get('name')));
            if ($item->getName() === '') {
                throw new \InvalidArgumentException('Eine Bezeichnung ist erforderlich.');
            }
            $item->setDescription($this->nullable($request->request->get('description')));
            $item->setQuantity((int) $request->request->get('quantity', 1));
            $item->setUnitPrice($money->parse((string) $request->request->get('unitPrice', '0')));
            $item->setPosition($quote->getItems()->count() + 1);
            $item->setItemType(QuoteItemType::Custom);
            $rate = $request->request->get('taxRate');
            $item->setTaxRate(is_scalar($rate) && trim((string) $rate) !== '' ? $money->parse((string) $rate) : null);
            $quote->addItem($item);
            $calculator->calculate($quote);
            $this->entityManager->flush();
        } catch (\InvalidArgumentException|\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('cardnext_admin_quote_edit', ['id' => $quote->getId()]);
    }

    #[Route('/offer/{quote}/item/{item}/remove', name: 'item_remove', methods: ['POST'])]
    public function removeItem(Quote $quote, QuoteItem $item, Request $request, QuoteCalculator $calculator): Response
    {
        $this->adminOnly();
        $this->checkCsrf('quote_edit_' . $quote->getId(), $request);
        $this->draftGuard->assertDraft($quote);
        $this->assertItem($quote, $item);
        $quote->removeItem($item);
        $calculator->calculate($quote);
        $this->entityManager->flush();

        return $this->redirectToRoute('cardnext_admin_quote_edit', ['id' => $quote->getId()]);
    }

    #[Route('/offer/{id}/ready', name: 'ready', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function ready(Quote $quote, Request $request, QuoteCalculator $calculator, QuoteOrderDataValidator $orderDataValidator): Response
    {
        $this->adminOnly();
        $this->checkCsrf('quote_edit_' . $quote->getId(), $request);
        $this->draftGuard->assertDraft($quote);
        if ($quote->getItems()->isEmpty()) {
            $this->addFlash('error', 'Ein Angebot ohne Position kann nicht fertig markiert werden.');
        } else {
            try {
                $orderDataValidator->assertCompleteForReady($quote);
                $calculator->calculate($quote);
                $now = new \DateTimeImmutable();
                if ($quote->getQuoteDate() === null) {
                    $quote->setQuoteDate($now->setTime(0, 0));
                } if ($quote->getReadyAt() === null) {
                    $quote->setReadyAt($now);
                } $quote->transitionTo(QuoteStatus::Ready);
                $quote->getQuoteRequest()->addHistory(new QuoteRequestHistory('quote_marked_ready', null, null, 'Angebot ' . $quote->getNumber() . ' v' . $quote->getVersion() . ' fertiggestellt'));
                $this->entityManager->flush();
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->redirectToRoute('cardnext_admin_quote_edit', ['id' => $quote->getId()]);
    }

    #[Route('/offer/{id}/send', name: 'send', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function send(Quote $quote, Request $request, QuoteOfferSender $sender, LoggerInterface $logger): Response
    {
        $this->adminOnly();
        $this->checkCsrf('quote_send_' . $quote->getId(), $request);

        try {
            $sender->send($quote);
            $this->addFlash('success', 'Angebot wurde versendet.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $logger->error('Quote offer sending failed', ['quoteId' => $quote->getId(), 'exceptionClass' => $e::class]);
            $this->addFlash('error', 'Das Angebot konnte nicht versendet werden.');
        }

        return $this->redirectToRoute('cardnext_admin_quote_edit', ['id' => $quote->getId()]);
    }

    #[Route('/offer/{id}/revision', name: 'revision', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function revision(Quote $quote, Request $request, QuoteRevisionFactory $factory): Response
    {
        $this->adminOnly();
        $this->checkCsrf('quote_revision_' . $quote->getId(), $request);
        $new = $this->entityManager->wrapInTransaction(function () use ($factory, $quote): Quote {
            $new = $factory->create($quote, $this->admin());
            $quote->getQuoteRequest()->addHistory(new QuoteRequestHistory('quote_revision_created', null, null, 'Angebot ' . $quote->getNumber() . ' v' . $new->getVersion() . ' erstellt'));

            return $new;
        });

        return $this->redirectToRoute('cardnext_admin_quote_edit', ['id' => $new->getId()]);
    }

    #[Route('/offer/{id}/order', name: 'order_create', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function createOrder(Quote $quote, Request $request, QuoteOrderConverter $converter): Response
    {
        $this->adminOnly();
        $this->checkCsrf('quote_order_create_' . $quote->getId(), $request);

        try {
            $converter->convert($quote, $this->admin());
            $this->addFlash('success', 'Bestellung wurde erfolgreich erstellt.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('cardnext_admin_quote_edit', ['id' => $quote->getId()]);
    }

    #[Route('/offer/{id}/pdf', name: 'pdf', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function pdf(Quote $quote, QuotePdfRenderer $renderer): Response
    {
        $this->adminOnly();
        if (!in_array($quote->getStatus(), [QuoteStatus::Ready, QuoteStatus::Sent, QuoteStatus::Accepted, QuoteStatus::Rejected, QuoteStatus::Superseded], true)) {
            return new Response('Ein finales PDF ist nur für fertige Angebote verfügbar.', Response::HTTP_CONFLICT);
        }
        $bytes = $renderer->render($quote);
        $quote->getQuoteRequest()->addHistory(new QuoteRequestHistory('quote_pdf_downloaded', null, null, 'Angebot ' . $quote->getNumber() . ' v' . $quote->getVersion() . ' als PDF heruntergeladen'));
        $this->entityManager->flush();
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '-', $quote->getNumber()) ?: 'Angebot';

        return new Response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Angebot-' . $safe . '-v' . $quote->getVersion() . '.pdf"']);
    }

    #[Route('/{id}/status', name: 'status', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function status(QuoteRequest $quote, Request $request): Response
    {
        $this->adminOnly();
        $this->checkCsrf('quote_status_' . $quote->getId(), $request);
        $next = QuoteRequestStatus::tryFrom((string) $request->request->get('status'));
        $old = $quote->getStatus();
        if (!$next || !$old->canTransitionTo($next)) {
            throw $this->createNotFoundException('Invalid quote status transition.');
        }
        $quote->setStatus($next);
        $quote->addHistory(new QuoteRequestHistory('status_changed', $old->value, $next->value));
        $this->entityManager->flush();

        return $this->redirectToRoute('cardnext_admin_quote_show', ['id' => $quote->getId()]);
    }

    private function adminOnly(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMINISTRATION_ACCESS');
    }

    private function admin(): ?AdminUser
    {
        $user = $this->getUser();

        return $user instanceof AdminUser ? $user : null;
    }

    private function checkCsrf(string $id, Request $request): void
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid($id, is_string($token) ? $token : '')) {
            throw $this->createAccessDeniedException();
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    private function itemOf(Quote $quote, int $id): QuoteItem
    {
        foreach ($quote->getItems() as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        throw $this->createNotFoundException();
    }

    private function assertItem(Quote $quote, QuoteItem $item): void
    {
        if ($item->getQuote() !== $quote) {
            throw $this->createNotFoundException();
        }
    }
}
