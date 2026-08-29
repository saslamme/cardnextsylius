<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer\Customer;
use App\Entity\Quote\QuoteRequest;
use App\Form\Type\Quote\QuoteRequestType;
use App\International\CardnextMarketRegistry;
use App\Service\Quote\QuoteCartService;
use App\Service\Quote\QuoteRequestMailer;
use App\Service\Quote\QuoteRequestSubmitter;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class QuoteCartController extends AbstractController
{
    public function __construct(
        private QuoteCartService $cart,
        private ChannelContextInterface $channels,
        private CardnextMarketRegistry $markets,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/{_locale}/angebotskorb', name: 'cardnext_shop_quote_cart', methods: ['GET', 'POST'], priority: 200)]
    public function index(Request $request, QuoteRequestSubmitter $submitter, QuoteRequestMailer $mailer): Response
    {
        $channel = $this->channel();
        $items = $this->cart->resolvedItems($channel);
        $quote = new QuoteRequest();
        $this->prefillQuote($quote, $channel, $request->getLocale());

        $storedToken = $request->getSession()->get('cardnext.quote_submission');
        if (!is_string($storedToken) || $storedToken === '') {
            $storedToken = Uuid::v4()->toRfc4122();
            $request->getSession()->set('cardnext.quote_submission', $storedToken);
        }

        $form = $this->createForm(QuoteRequestType::class, $quote);
        $form->add('_submission', HiddenType::class, [
            'mapped' => false,
            'data' => $storedToken,
        ]);
        $form->handleRequest($request);

        $showValidationError = $form->isSubmitted() && !$form->isValid();
        $showSubmissionError = false;

        if ($form->isSubmitted() && $form->isValid()) {
            $honeypot = $form->get('website')->getData();
            $isHoneypotEmpty = $honeypot === null || $honeypot === '';

            if ($isHoneypotEmpty && $items !== []) {
                $submittedToken = $form->get('_submission')->getData();
                if (!is_string($submittedToken) || $submittedToken === '' || !hash_equals($storedToken, $submittedToken)) {
                    $showSubmissionError = true;
                } else {
                    try {
                        $submitter->submit($quote, $channel, $request->getLocale(), $items);
                        $request->getSession()->remove('cardnext.quote_submission');
                        $this->cart->clear();
                        $mailer->send($quote);

                        return $this->redirectToRoute('cardnext_shop_quote_confirmation', [
                            '_locale' => $request->getLocale(),
                            'number' => $quote->getNumber(),
                        ]);
                    } catch (\DomainException $exception) {
                        $this->logger->warning('quote submission rejected', [
                            'reason' => $exception->getMessage(),
                            'channel' => $channel->getCode(),
                            'locale' => $request->getLocale(),
                            'item_count' => count($items),
                        ]);

                        if ($exception->getMessage() !== 'Quote cart is empty.') {
                            throw $exception;
                        }

                        $this->addFlash('error', 'cardnext.quote.empty');
                    }
                }
            }
        }

        return $this->render('shop/quote/cart.html.twig', [
            'items' => $items,
            'form' => $form,
            'currency' => $channel->getBaseCurrency()?->getCode(),
            'showValidationError' => $showValidationError,
            'showSubmissionError' => $showSubmissionError,
        ], $showValidationError ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{_locale}/angebotskorb/hinzufuegen', name: 'cardnext_shop_quote_cart_add', methods: ['POST'], priority: 200)]
    public function add(Request $request): Response
    {
        $this->csrf($request, 'quote_add');
        $added = $this->cart->add(
            $request->request->getString('variantCode'),
            $request->request->getInt('quantity', 1),
            $this->channel(),
        );
        $this->addFlash($added ? 'success' : 'error', $added ? 'cardnext.quote.added' : 'cardnext.quote.invalid_item');

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('cardnext_shop_quote_cart', ['_locale' => $request->getLocale()]));
    }

    #[Route('/{_locale}/angebotskorb/aktualisieren', name: 'cardnext_shop_quote_cart_update', methods: ['POST'], priority: 200)]
    public function update(Request $request): Response
    {
        $this->csrf($request, 'quote_update');
        $this->cart->update($request->request->getString('variantCode'), $request->request->getInt('quantity'), $this->channel());

        return $this->redirectToRoute('cardnext_shop_quote_cart', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{_locale}/angebotskorb/entfernen', name: 'cardnext_shop_quote_cart_remove', methods: ['POST'], priority: 200)]
    public function remove(Request $request): Response
    {
        $this->csrf($request, 'quote_remove');
        $this->cart->remove($request->request->getString('variantCode'), $this->channel());

        return $this->redirectToRoute('cardnext_shop_quote_cart', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{_locale}/angebotskorb/leeren', name: 'cardnext_shop_quote_cart_clear', methods: ['POST'], priority: 200)]
    public function clear(Request $request): Response
    {
        $this->csrf($request, 'quote_clear');
        $this->cart->clear();

        return $this->redirectToRoute('cardnext_shop_quote_cart', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{_locale}/angebotsanfrage/{number}/bestaetigung', name: 'cardnext_shop_quote_confirmation', requirements: ['number' => 'AN-\\d{4}-\\d{5,}'], methods: ['GET'], priority: 200)]
    public function confirmation(string $number): Response
    {
        return $this->render('shop/quote/confirmation.html.twig', ['number' => $number]);
    }

    private function prefillQuote(QuoteRequest $quote, ChannelInterface $channel, string $locale): void
    {
        $countryCode = $this->markets->get($channel->getCode() ?? '')?->countryCode;
        foreach ($channel->getCountries() as $country) {
            $countryCode ??= $country->getCode();
            break;
        }
        if ($countryCode === null && preg_match('/_([A-Z]{2})$/', $channel->getCode() ?? '', $matches) === 1) {
            $countryCode = $matches[1];
        }
        $countryCode ??= \Locale::getRegion($locale) ?: strtoupper(substr($locale, 0, 2));
        $quote->setCountryCode($countryCode);

        $user = $this->getUser();
        if (!method_exists($user ?? new \stdClass(), 'getCustomer') || !(($customer = $user->getCustomer()) instanceof Customer)) {
            return;
        }

        $quote->setCustomer($customer);
        $quote->setEmail($customer->getEmail() ?? '');
        $quote->setContactName(trim(($customer->getFirstName() ?? '') . ' ' . ($customer->getLastName() ?? '')));
        $address = $customer->getDefaultAddress();
        if ($address === null) {
            return;
        }
        $quote->setCompany($address->getCompany() ?? '');
        $quote->setStreet($address->getStreet());
        $quote->setPostalCode($address->getPostcode());
        $quote->setCity($address->getCity());
        if ($address->getCountryCode() !== null) {
            $quote->setCountryCode($address->getCountryCode());
        }
    }

    private function channel(): ChannelInterface
    {
        $channel = $this->channels->getChannel();
        if (!$channel instanceof ChannelInterface) {
            throw new \LogicException('A core sales channel is required.');
        }

        return $channel;
    }

    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
