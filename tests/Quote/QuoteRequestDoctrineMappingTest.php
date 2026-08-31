<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Quote\QuoteRequest;
use App\Entity\Quote\QuoteRequestHistory;
use App\Entity\Quote\QuoteRequestItem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QuoteRequestDoctrineMappingTest extends KernelTestCase
{
    public function testChildAssociationsUseTheDeployedSnakeCaseJoinColumn(): void
    {
        $driver = new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity/Quote']);

        foreach ([QuoteRequestItem::class, QuoteRequestHistory::class] as $class) {
            $metadata = new ClassMetadata($class);
            $metadata->initializeReflection(new RuntimeReflectionService());
            $driver->loadMetadataForClass($class, $metadata);

            self::assertSame(
                'quote_request_id',
                $metadata->getSingleAssociationJoinColumnName('quoteRequest'),
                $class . '::$quoteRequest',
            );
        }
    }

    public function testQuoteWithItemAndHistoryCanBePersistedAndLoadedAgain(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(ManagerRegistry::class);
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $entityManager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->getConnection()->beginTransaction();

        try {
            $quote = new QuoteRequest();
            $quote->setNumber('AN-TEST-' . bin2hex(random_bytes(4)));
            $quote->setChannelCode('WEB');
            $quote->setLocaleCode('en_US');
            $quote->setCurrencyCode('EUR');
            $quote->setCompany('Cardnext test');
            $quote->setContactName('Doctrine test');
            $quote->setEmail('doctrine@example.com');
            $quote->setCountryCode('DE');
            $quote->setRequestedDeliveryDate(new \DateTimeImmutable('2030-01-02'));

            $item = new QuoteRequestItem();
            $item->setProductCode('TEST-PRODUCT');
            $item->setVariantCode('TEST-VARIANT');
            $item->setProductName('Test product');
            $item->setQuantity(2);
            $item->setUnitPrice(100);
            $item->setLineTotal(200);
            $item->setCurrencyCode('EUR');
            $quote->addItem($item);

            $history = new QuoteRequestHistory('created', null, 'new');
            $quote->addHistory($history);

            $entityManager->persist($quote);
            $entityManager->flush();
            $quoteId = $quote->getId();
            self::assertNotNull($quoteId);

            $entityManager->clear();
            $reloaded = $entityManager->find(QuoteRequest::class, $quoteId);

            self::assertInstanceOf(QuoteRequest::class, $reloaded);
            self::assertCount(1, $reloaded->getItems());
            self::assertCount(1, $reloaded->getHistory());
            $reloadedItem = $reloaded->getItems()->first();
            $reloadedHistory = $reloaded->getHistory()->first();
            self::assertNotFalse($reloadedItem);
            self::assertNotFalse($reloadedHistory);
            self::assertSame('TEST-PRODUCT', $reloadedItem->getProductCode());
            self::assertSame('created', $reloadedHistory->getType());
            self::assertEquals(new \DateTimeImmutable('2030-01-02'), $reloaded->getRequestedDeliveryDate());
        } finally {
            $entityManager->getConnection()->rollBack();
        }
    }
}
