<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Addressing\Address;
use App\Entity\Channel\Channel;
use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteItem;
use App\Entity\Quote\QuoteRequestHistory;
use App\Entity\User\AdminUser;
use App\Enum\Quote\QuoteItemType;
use App\Enum\Quote\QuoteRequestStatus;
use App\Enum\Quote\QuoteStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Sylius\Bundle\CoreBundle\Factory\OrderFactoryInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\AdjustmentInterface as CoreAdjustmentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\Model\AdjustmentInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Resource\Factory\FactoryInterface;

final readonly class QuoteOrderConverter
{
    /**
     * @param OrderFactoryInterface<Order> $orderFactory
     * @param CartItemFactoryInterface<OrderItem> $orderItemFactory
     * @param FactoryInterface<AdjustmentInterface> $adjustmentFactory
     * @param FactoryInterface<Address> $addressFactory
     * @param ObjectRepository<Channel> $channelRepository
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderFactoryInterface $orderFactory,
        private CartItemFactoryInterface $orderItemFactory,
        private OrderItemQuantityModifierInterface $quantityModifier,
        private FactoryInterface $adjustmentFactory,
        private FactoryInterface $addressFactory,
        private ObjectRepository $channelRepository,
        private OrderNumberAssignerInterface $numberAssigner,
        private QuoteOrderDataValidator $orderDataValidator,
    ) {
    }

    public function convert(Quote $quote, ?AdminUser $admin = null): Order
    {
        if ($quote->getOrder() !== null) {
            return $quote->getOrder();
        }

        return $this->entityManager->wrapInTransaction(function () use ($quote, $admin): Order {
            if ($quote->getId() !== null) {
                $locked = $this->entityManager->find(Quote::class, $quote->getId(), LockMode::PESSIMISTIC_WRITE);
                if (!$locked instanceof Quote) {
                    throw new \DomainException('Das Angebot wurde nicht gefunden.');
                }
                $quote = $locked;
            }
            if ($quote->getOrder() !== null) {
                return $quote->getOrder();
            }
            $this->validate($quote);

            $channel = $this->channelRepository->findOneBy(['code' => $quote->getChannelCode()]);
            if (!$channel instanceof Channel) {
                throw new \DomainException('Der im Angebot gespeicherte Channel existiert nicht.');
            }
            $currencies = array_map(static fn ($currency): ?string => $currency->getCode(), $channel->getCurrencies()->toArray());
            $baseCurrency = $channel->getBaseCurrency()?->getCode();
            if ($quote->getCurrencyCode() !== $baseCurrency && !in_array($quote->getCurrencyCode(), $currencies, true)) {
                throw new \DomainException('Die Angebotswährung ist für den gespeicherten Channel nicht verfügbar.');
            }

            $customer = $quote->getCustomer();
            if (!$customer instanceof Customer) {
                throw new \DomainException('Die Bestellung kann nicht erstellt werden, da dem Angebot kein Kundenkonto zugeordnet ist.');
            }

            $order = $this->orderFactory->createNew();
            $order->setChannel($channel);
            $order->setLocaleCode($quote->getLocaleCode());
            $order->setCurrencyCode($quote->getCurrencyCode());
            $order->setCustomer($customer);
            $order->setBillingAddress($this->address($quote));
            $order->setShippingAddress($this->address($quote));

            foreach ($quote->getItems() as $quoteItem) {
                $this->addQuoteItem($order, $quoteItem, $quote);
            }
            $this->addAdjustment($order, 'cardnext_quote_service', 'Service', $quote->getServiceTotal());
            $this->addAdjustment($order, 'cardnext_quote_shipping', 'Versand laut Angebot – Versandmethode noch nicht zugeordnet', $quote->getShippingTotal());
            $this->addAdjustment($order, CoreAdjustmentInterface::TAX_ADJUSTMENT, 'MwSt. laut Angebot ' . $quote->getNumber(), $quote->getTaxTotal());

            if ($order->getTotal() !== $quote->getGrandTotal()) {
                throw new \DomainException(sprintf('Die Bestellung konnte nicht erzeugt werden, da die berechnete Gesamtsumme vom angenommenen Angebot abweicht (Angebot: %d, Bestellung: %d, Differenz: %d).', $quote->getGrandTotal(), $order->getTotal(), $order->getTotal() - $quote->getGrandTotal()));
            }

            $order->completeCheckout();
            $order->setCheckoutState(OrderCheckoutStates::STATE_COMPLETED);
            $order->setState(OrderInterface::STATE_NEW);
            $this->numberAssigner->assignNumber($order);
            $this->entityManager->persist($order);
            $quote->markConvertedToOrder($order, $admin, new \DateTimeImmutable());
            $request = $quote->getQuoteRequest();
            if ($request->getStatus() !== QuoteRequestStatus::Closed && $request->getStatus()->canTransitionTo(QuoteRequestStatus::Closed)) {
                $request->setStatus(QuoteRequestStatus::Closed);
            }
            $request->addHistory(new QuoteRequestHistory('order_created', null, null, sprintf('Bestellung %s aus Angebot %s v%d erstellt', $order->getNumber(), $quote->getNumber(), $quote->getVersion())));
            $this->entityManager->flush();

            return $order;
        });
    }

    private function validate(Quote $quote): void
    {
        if ($quote->getStatus() !== QuoteStatus::Accepted) {
            throw new \DomainException('Nur ein angenommenes Angebot kann in eine Bestellung umgewandelt werden.');
        }
        $this->orderDataValidator->assertCompleteForOrder($quote);
        if ($quote->getItems()->isEmpty()) {
            throw new \DomainException('Ein Angebot ohne Positionen kann nicht in eine Bestellung umgewandelt werden.');
        }
        if (trim($quote->getChannelCode()) === '') {
            throw new \DomainException('Im Angebot fehlt der Channel.');
        }
        if (trim($quote->getCurrencyCode()) === '') {
            throw new \DomainException('Im Angebot fehlt die Währung.');
        }
        if (trim($quote->getLocaleCode()) === '') {
            throw new \DomainException('Im Angebot fehlt die Sprache.');
        }
        foreach ($quote->getItems() as $item) {
            match ($item->getItemType()) {
                QuoteItemType::Product => $this->validateProductItem($item),
                QuoteItemType::Custom => null,
                QuoteItemType::Service, QuoteItemType::Shipping => throw $this->unsupportedItemType($item),
            };
        }
    }

    private function addQuoteItem(Order $order, QuoteItem $quoteItem, Quote $quote): void
    {
        match ($quoteItem->getItemType()) {
            QuoteItemType::Product => $this->addProductItem($order, $quoteItem, $quote),
            QuoteItemType::Custom => $this->addCustomItem($order, $quoteItem),
            QuoteItemType::Service, QuoteItemType::Shipping => throw $this->unsupportedItemType($quoteItem),
        };
    }

    private function validateProductItem(QuoteItem $item): void
    {
        if ($item->getVariant() === null) {
            throw new \DomainException(sprintf('Die Produktvariante der Position „%s“ existiert nicht mehr.', $item->getName()));
        }
    }

    private function addProductItem(Order $order, QuoteItem $quoteItem, Quote $quote): void
    {
        $item = $this->orderItemFactory->createNew();
        $item->setVariant($quoteItem->getVariant());
        $item->setProductName($quoteItem->getName());
        // The original unit price plus the locked discount reproduces the accepted net snapshot exactly.
        $item->setUnitPrice($quoteItem->getOriginalUnitPrice() ?? $quoteItem->getUnitPrice());
        $this->quantityModifier->modify($item, $quoteItem->getQuantity());
        $order->addItem($item);
        $this->addAdjustment($item, 'cardnext_quote_discount', 'Angebotsrabatt ' . $quote->getNumber(), -$quoteItem->getLineDiscount());
    }

    private function addCustomItem(Order $order, QuoteItem $quoteItem): void
    {
        $this->addAdjustment($order, 'cardnext_quote_custom', $quoteItem->getName(), $quoteItem->getLineTotal(), ['description' => $quoteItem->getDescription()]);
    }

    private function unsupportedItemType(QuoteItem $item): \DomainException
    {
        $message = match ($item->getItemType()) {
            QuoteItemType::Service => 'Serviceleistungen werden über den Servicebetrag des Angebots abgebildet und dürfen nicht zusätzlich als Angebotsposition vorhanden sein.',
            QuoteItemType::Shipping => 'Versandkosten werden über den Versandbetrag des Angebots abgebildet und dürfen nicht zusätzlich als Angebotsposition vorhanden sein.',
            QuoteItemType::Product, QuoteItemType::Custom => sprintf('Die Angebotsposition „%s“ kann nicht in eine Bestellung übernommen werden.', $item->getName()),
        };

        return new \DomainException(sprintf('%s Betroffene Position: „%s“.', $message, $item->getName()));
    }

    private function address(Quote $quote): Address
    {
        $address = $this->addressFactory->createNew();
        [$firstName, $lastName] = self::splitContactName($quote->getCustomerContactName());
        $address->setFirstName($firstName);
        $address->setLastName($lastName);
        $address->setCompany($quote->getCustomerCompany() ?: null);
        $address->setStreet(trim(($quote->getCustomerStreet() ?? '') . ' ' . ($quote->getCustomerHouseNumber() ?? '')));
        $address->setPostcode($quote->getCustomerPostalCode());
        $address->setCity($quote->getCustomerCity());
        $address->setCountryCode($quote->getCustomerCountryCode() === null ? null : strtoupper($quote->getCustomerCountryCode()));
        $address->setPhoneNumber($quote->getCustomerPhone());

        return $address;
    }

    /**
     * @param Order|OrderItem $adjustable
     * @param array<string, mixed> $details
     */
    private function addAdjustment(object $adjustable, string $type, string $label, int $amount, array $details = []): void
    {
        if ($amount === 0) {
            return;
        }
        $adjustment = $this->adjustmentFactory->createNew();
        $adjustment->setType($type);
        $adjustment->setLabel($label);
        $adjustment->setAmount($amount);
        $adjustment->setDetails($details);
        $adjustable->addAdjustment($adjustment);
        $adjustment->lock();
    }

    /** @return array{string, string} */
    public static function splitContactName(string $contactName): array
    {
        $parts = preg_split('/\s+/u', trim($contactName), 2, \PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) !== 2) {
            throw new \DomainException('Der Ansprechpartner muss Vor- und Nachname enthalten.');
        }

        return [$parts[0], $parts[1]];
    }
}
