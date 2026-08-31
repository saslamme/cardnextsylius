<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteItem;
use App\Entity\Quote\QuoteRequest;
use App\Entity\Quote\QuoteRequestHistory;
use App\Entity\User\AdminUser;
use App\Enum\Quote\QuoteItemType;
use App\Enum\Quote\QuoteRequestStatus;
use Doctrine\ORM\EntityManagerInterface;

final class QuoteFactory
{
    public function __construct(private EntityManagerInterface $entityManager, private QuoteCalculator $calculator, private QuoteTaxRateResolver $taxRates)
    {
    }

    public function createFromRequest(QuoteRequest $request, ?AdminUser $admin = null): Quote
    {
        $existing = $request->getActiveQuote();
        if ($existing !== null) {
            return $existing;
        }

        $quote = new Quote();
        $request->addQuote($quote);
        $quote->setNumber(preg_replace('/^AN-/', 'AG-', $request->getNumber()) ?? 'AG-' . $request->getNumber());
        $quote->setVersion(1);
        $quote->setChannelCode($request->getChannelCode());
        $quote->setLocaleCode($request->getLocaleCode());
        $quote->setCurrencyCode($request->getCurrencyCode());
        $quote->setCustomerCompany($request->getCompany());
        $quote->setCustomerContactName($request->getContactName());
        $quote->setCustomerEmail($request->getEmail());
        $quote->setCustomerStreet($request->getStreet());
        $quote->setCustomerHouseNumber($request->getHouseNumber());
        $quote->setCustomerPostalCode($request->getPostalCode());
        $quote->setCustomerCity($request->getCity());
        $quote->setCustomerCountryCode($request->getCountryCode());
        $quote->setCustomerNumber($request->getCustomerNumber());
        $quote->setCustomerPhone($request->getPhone());
        $quote->setProjectReference($request->getProjectReference());
        $quote->setCustomerPurchaseOrderNumber($request->getCustomerPurchaseOrderNumber());
        $quote->setDefaultTaxRate($this->taxRates->resolve($request->getChannelCode()));
        $quote->setCreatedBy($admin);
        $quote->setUpdatedBy($admin);

        foreach ($request->getItems() as $source) {
            $item = new QuoteItem();
            $item->setPosition($source->getPosition());
            $item->setProduct($source->getProduct());
            $item->setVariant($source->getVariant());
            $item->setProductCode($source->getProductCode());
            $item->setVariantCode($source->getVariantCode());
            $item->setName($source->getProductName() . ($source->getVariantName() ? ' – ' . $source->getVariantName() : ''));
            $item->setQuantity($source->getQuantity());
            $item->setOriginalUnitPrice($source->getUnitPrice());
            $item->setUnitPrice($source->getUnitPrice());
            $item->setItemType(QuoteItemType::Product);
            $quote->addItem($item);
        }

        $this->calculator->calculate($quote);
        $request->setStatus(QuoteRequestStatus::InProgress);
        $request->addHistory(new QuoteRequestHistory('quote_created', null, QuoteRequestStatus::InProgress->value, 'Angebot ' . $quote->getNumber() . ' erstellt'));
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        return $quote;
    }
}
