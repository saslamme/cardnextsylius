<?php
declare(strict_types=1);
namespace App\Service\Quote;
use App\Entity\Quote\Quote;
use App\Entity\Quote\QuoteItem;
use App\Entity\User\AdminUser;
use App\Enum\Quote\QuoteStatus;
use Doctrine\ORM\EntityManagerInterface;
final class QuoteRevisionFactory
{
    public function __construct(private EntityManagerInterface $em, private QuoteCalculator $calculator) {}
    public function create(Quote $source, ?AdminUser $admin = null): Quote
    {
        if ($source->getStatus() !== QuoteStatus::Ready) throw new \DomainException('Eine neue Version kann nur aus einem fertigen Angebot erstellt werden.');
        $max = (int) $this->em->createQueryBuilder()->select('MAX(q.version)')->from(Quote::class, 'q')->where('q.number = :number')->setParameter('number', $source->getNumber())->getQuery()->getSingleScalarResult();
        $q = new Quote(); $source->getQuoteRequest()->addQuote($q); $q->setNumber($source->getNumber()); $q->setVersion($max + 1);
        $q->setChannelCode($source->getChannelCode()); $q->setLocaleCode($source->getLocaleCode()); $q->setCurrencyCode($source->getCurrencyCode());
        $q->setCustomerCompany($source->getCustomerCompany()); $q->setCustomerContactName($source->getCustomerContactName()); $q->setCustomerEmail($source->getCustomerEmail());
        $q->setCustomerStreet($source->getCustomerStreet()); $q->setCustomerHouseNumber($source->getCustomerHouseNumber()); $q->setCustomerPostalCode($source->getCustomerPostalCode()); $q->setCustomerCity($source->getCustomerCity()); $q->setCustomerCountryCode($source->getCustomerCountryCode()); $q->setCustomerNumber($source->getCustomerNumber()); $q->setCustomerPhone($source->getCustomerPhone()); $q->setProjectReference($source->getProjectReference()); $q->setCustomerPurchaseOrderNumber($source->getCustomerPurchaseOrderNumber());
        $q->setValidUntil($source->getValidUntil()); $q->setDeliveryTerms($source->getDeliveryTerms()); $q->setPaymentTerms($source->getPaymentTerms()); $q->setCustomerNote($source->getCustomerNote()); $q->setInternalNote($source->getInternalNote()); $q->setDefaultTaxRate($source->getDefaultTaxRate()); $q->setTaxNote($source->getTaxNote()); $q->setShippingTotal($source->getShippingTotal()); $q->setServiceTotal($source->getServiceTotal());
        $q->setCreatedBy($admin); $q->setUpdatedBy($admin);
        foreach ($source->getItems() as $old) { $item = new QuoteItem(); $item->setPosition($old->getPosition()); $item->setProduct($old->getProduct()); $item->setVariant($old->getVariant()); $item->setProductCode($old->getProductCode()); $item->setVariantCode($old->getVariantCode()); $item->setName($old->getName()); $item->setDescription($old->getDescription()); $item->setQuantity($old->getQuantity()); $item->setOriginalUnitPrice($old->getOriginalUnitPrice()); $item->setUnitPrice($old->getUnitPrice()); $item->setDiscountPercent($old->getDiscountPercent()); $item->setDiscountAmount($old->getDiscountAmount()); $item->setTaxRate($old->getTaxRate()); $item->setItemType($old->getItemType()); $q->addItem($item); }
        $this->calculator->calculate($q); $this->em->persist($q); return $q;
    }
}
