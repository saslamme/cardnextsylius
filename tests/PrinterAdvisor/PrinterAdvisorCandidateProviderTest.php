<?php

declare(strict_types=1);

namespace App\Tests\PrinterAdvisor;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Product\PrinterAdvisorProfile;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\PrinterAdvisor\PrinterAdvisorCandidateProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PrinterAdvisorCandidateProviderTest extends KernelTestCase
{
    public function testForChannelExecutesRealDqlAndOnlyReturnsEligibleProducts(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(ManagerRegistry::class)->getManager();
        $entityManager->getConnection()->beginTransaction();

        try {
            $suffix = bin2hex(random_bytes(6));
            $channel = $this->createChannel('advisor-main-' . $suffix);
            $otherChannel = $this->createChannel('advisor-other-' . $suffix);

            $eligible = $this->createProduct('advisor-eligible-' . $suffix, $channel, true, 12_345);
            $disabledProfile = $this->createProduct('advisor-disabled-profile-' . $suffix, $channel, false, 23_456);
            $wrongChannel = $this->createProduct('advisor-wrong-channel-' . $suffix, $otherChannel, true, 34_567);
            $withoutPrice = $this->createProduct('advisor-without-price-' . $suffix, $channel, true, null);

            $entityManager->persist($channel);
            $entityManager->persist($otherChannel);
            $entityManager->persist($eligible);
            $entityManager->persist($disabledProfile);
            $entityManager->persist($wrongChannel);
            $entityManager->persist($withoutPrice);
            $entityManager->flush();

            $provider = self::getContainer()->get(PrinterAdvisorCandidateProvider::class);
            self::assertInstanceOf(PrinterAdvisorCandidateProvider::class, $provider);

            // Calling the real provider compiles the DQL against Sylius metadata and executes its SQL.
            $candidates = $provider->forChannel($channel);
            $candidateProducts = array_map(static fn ($candidate): Product => $candidate->product, $candidates);

            self::assertContains($eligible, $candidateProducts);
            self::assertNotContains($disabledProfile, $candidateProducts);
            self::assertNotContains($wrongChannel, $candidateProducts);
            self::assertNotContains($withoutPrice, $candidateProducts);
        } finally {
            $entityManager->getConnection()->rollBack();
        }
    }

    private function createChannel(string $code): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($code);
        $channel->setEnabled(true);

        return $channel;
    }

    private function createProduct(string $code, Channel $channel, bool $profileEnabled, ?int $price): Product
    {
        $product = new Product();
        $product->setCode($code);
        $product->setEnabled(true);
        $product->addChannel($channel);

        $profile = new PrinterAdvisorProfile();
        $profile->setEnabled($profileEnabled);
        $product->setPrinterAdvisorProfile($profile);

        $variant = new ProductVariant();
        $variant->setCode($code . '-variant');
        $variant->setEnabled(true);
        $product->addVariant($variant);

        $pricing = new ChannelPricing();
        $pricing->setChannelCode($channel->getCode());
        $pricing->setPrice($price);
        $variant->addChannelPricing($pricing);

        return $product;
    }
}
