<?php

declare(strict_types=1);

namespace App\Tests\PrinterAdvisor;

use App\Entity\Product\PrinterAdvisorProfile;
use App\Entity\Product\Product;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PrinterAdvisorProfileDoctrineTest extends KernelTestCase
{
    public function testMappingMatchesTheDeployedSnakeCaseSchema(): void
    {
        $metadata = new ClassMetadata(PrinterAdvisorProfile::class);
        (new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity/Product']))
            ->loadMetadataForClass(PrinterAdvisorProfile::class, $metadata);

        self::assertSame('cardnext_printer_advisor_profile', $metadata->getTableName());
        self::assertSame('product_id', $metadata->getSingleAssociationJoinColumnName('product'));
        $expectedColumns = [
            'id' => 'id',
            'enabled' => 'enabled',
            'priority' => 'priority',
            'minAnnualVolume' => 'min_annual_volume',
            'maxAnnualVolume' => 'max_annual_volume',
            'singleSided' => 'single_sided',
            'duplex' => 'duplex',
            'magneticStripe' => 'magnetic_stripe',
            'contactChip' => 'contact_chip',
            'rfidNfc' => 'rfid_nfc',
            'directPrinting' => 'direct_printing',
            'retransfer' => 'retransfer',
            'lamination' => 'lamination',
            'highDurability' => 'high_durability',
            'performanceClass' => 'performance_class',
        ];

        foreach ($expectedColumns as $field => $column) {
            self::assertSame($column, $metadata->getColumnName($field), $field);
        }
    }

    public function testProfileCanBePersistedAndLoadedAgain(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(ManagerRegistry::class)->getManager();
        $entityManager->getConnection()->beginTransaction();

        try {
            $product = new Product();
            $product->setCode('advisor-mapping-' . bin2hex(random_bytes(6)));

            $profile = new PrinterAdvisorProfile();
            $profile->setEnabled(true);
            $profile->setPriority(17);
            $profile->setMinAnnualVolume(1_234);
            $profile->setMaxAnnualVolume(56_789);
            $profile->setSingleSided(false);
            $profile->setDuplex(true);
            $profile->setMagneticStripe(true);
            $profile->setContactChip(true);
            $profile->setRfidNfc(true);
            $profile->setDirectPrinting(false);
            $profile->setRetransfer(true);
            $profile->setLamination(true);
            $profile->setHighDurability(true);
            $profile->setPerformanceClass(5);
            $product->setPrinterAdvisorProfile($profile);

            $entityManager->persist($product);
            $entityManager->flush();
            $profileId = $profile->getId();
            self::assertNotNull($profileId);

            $entityManager->clear();
            $reloaded = $entityManager->find(PrinterAdvisorProfile::class, $profileId);

            self::assertInstanceOf(PrinterAdvisorProfile::class, $reloaded);
            self::assertSame(1_234, $reloaded->getMinAnnualVolume());
            self::assertSame(56_789, $reloaded->getMaxAnnualVolume());
            self::assertFalse($reloaded->isSingleSided());
            self::assertTrue($reloaded->hasMagneticStripe());
            self::assertTrue($reloaded->hasContactChip());
            self::assertTrue($reloaded->hasRfidNfc());
            self::assertFalse($reloaded->isDirectPrinting());
            self::assertTrue($reloaded->hasHighDurability());
            self::assertSame(5, $reloaded->getPerformanceClass());
        } finally {
            $entityManager->getConnection()->rollBack();
        }
    }
}
