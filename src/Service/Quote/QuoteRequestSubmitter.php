<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Entity\Quote\QuoteRequest;
use App\Entity\Quote\QuoteRequestHistory;
use App\Entity\Quote\QuoteRequestItem;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class QuoteRequestSubmitter
{
    public function __construct(
        private EntityManagerInterface $em,
        private QuoteNumberGenerator $numbers,
    ) {
    }

    /**
     * @param list<array{variant: ProductVariant, quantity: int, unitPrice: int, lineTotal: int}> $items
     */
    public function submit(QuoteRequest $quote, ChannelInterface $channel, string $locale, array $items): QuoteRequest
    {
        if ($items === []) {
            throw new \DomainException('Quote cart is empty.');
        }

        $this->em->wrapInTransaction(function () use ($quote, $channel, $locale, $items): void {
            $quote->setNumber($this->numbers->next());
            $quote->setChannelCode((string) $channel->getCode());
            $quote->setLocaleCode($locale);
            $currency = (string) $channel->getBaseCurrency()?->getCode();
            $quote->setCurrencyCode($currency);
            $subtotal = 0;

            foreach ($items as $position => $row) {
                $variant = $row['variant'];
                $product = $variant->getProduct();
                $item = new QuoteRequestItem();
                if ($product instanceof Product) {
                    $item->setProduct($product);
                }
                $item->setVariant($variant);
                $item->setProductCode((string) $product?->getCode());
                $item->setVariantCode((string) $variant->getCode());
                $item->setProductName((string) $product?->getName());
                $item->setVariantName($variant->getName());
                $item->setManufacturerName($product instanceof Product ? $product->getManufacturer()?->getName() : null);
                $item->setQuantity($row['quantity']);
                $item->setUnitPrice($row['unitPrice']);
                $item->setLineTotal($row['lineTotal']);
                $item->setCurrencyCode($currency);
                $item->setPosition($position);
                $quote->addItem($item);
                $subtotal += $row['lineTotal'];
            }

            $quote->setEstimatedSubtotal($subtotal);
            $quote->setEstimatedTotal($subtotal);
            $quote->addHistory(new QuoteRequestHistory('created', null, $quote->getStatus()->value, 'Angebotsanfrage erstellt'));
            $this->em->persist($quote);
            $this->em->flush();
        });

        return $quote;
    }
}
