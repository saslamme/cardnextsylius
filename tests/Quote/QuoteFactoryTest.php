<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Entity\Quote\QuoteRequest;
use App\Entity\Quote\QuoteRequestItem;
use App\Enum\Quote\QuoteRequestStatus;
use App\Service\Quote\QuoteCalculator;
use App\Service\Quote\QuoteFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class QuoteFactoryTest extends TestCase
{
    public function testCreatesTwoIndependentItemSnapshotsWithoutChangingRequestItems(): void
    {
        $request = new QuoteRequest();
        $request->setNumber('AN-2026-00042'); $request->setChannelCode('DE_WEB'); $request->setLocaleCode('de_DE'); $request->setCurrencyCode('EUR');
        $request->setCompany('Muster GmbH'); $request->setContactName('Max Mustermann'); $request->setEmail('max@example.com');
        foreach ([114900, 14900] as $position => $price) {
            $source = new QuoteRequestItem(); $source->setPosition($position + 1); $source->setProductCode('P'.$position); $source->setVariantCode('V'.$position); $source->setProductName('Produkt '.$position); $source->setQuantity($position + 1); $source->setUnitPrice($price); $source->setLineTotal($price * ($position + 1)); $source->setCurrencyCode('EUR');
            $request->addItem($source);
        }
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist'); $entityManager->expects(self::once())->method('flush');

        $quote = (new QuoteFactory($entityManager, new QuoteCalculator()))->createFromRequest($request);

        self::assertSame('AG-2026-00042', $quote->getNumber()); self::assertCount(2, $quote->getItems());
        self::assertSame(114900, $quote->getItems()->first()->getOriginalUnitPrice()); self::assertSame(114900, $quote->getItems()->first()->getUnitPrice());
        self::assertSame(114900, $request->getItems()->first()->getUnitPrice()); self::assertSame(QuoteRequestStatus::InProgress, $request->getStatus());
    }
}
