<?php

declare(strict_types=1);

namespace App\Tests\Leasing;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Leasing\LeasingConfiguration;
use App\Entity\Leasing\LeasingFactor;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LeasingInquiryControllerTest extends WebTestCase
{
    private const PRODUCT_CODE = 'ZEBRA_ZC35000C000EM00';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollBack();
        }

        parent::tearDown();
    }

    public function testEligibleProductRendersInquiryPageInResolvedStorefrontChannel(): void
    {
        $this->createScenario();

        $this->requestInquiry();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Leasing');
        self::assertSelectorExists('form select[name="duration"] option[value="24"][selected]');
    }

    /** @return iterable<string, array{array<string, bool|int>, string}> */
    public static function ineligibleScenarios(): iterable
    {
        yield 'leasing globally disabled' => [['globalEnabled' => false], self::PRODUCT_CODE];
        yield 'product not eligible for leasing' => [['productEnabled' => false], self::PRODUCT_CODE];
        yield 'no pricing for resolved channel' => [['withPricing' => false], self::PRODUCT_CODE];
        yield 'price below minimum' => [['price' => 49999], self::PRODUCT_CODE];
        yield 'no active factors' => [['factorActive' => false], self::PRODUCT_CODE];
        yield 'unknown product code' => [[], 'UNKNOWN_PRODUCT_CODE'];
    }

    /** @param array<string, bool|int> $options */
    #[DataProvider('ineligibleScenarios')]
    public function testIneligibleInquiryReturnsNotFound(array $options, string $code): void
    {
        $this->createScenario($options);

        $this->requestInquiry($code);

        self::assertResponseStatusCodeSame(404);
    }

    /** @param array<string, bool|int> $options */
    private function createScenario(array $options = []): void
    {
        $channel = $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => 'CARDNEXT_DE']);
        self::assertInstanceOf(Channel::class, $channel, 'The functional test fixtures must provide CARDNEXT_DE.');
        $channel->setEnabled(true);
        $channel->setHostname('www.cardnext.de');

        $configuration = $this->entityManager->find(LeasingConfiguration::class, 1) ?? new LeasingConfiguration();
        $configuration->setEnabled((bool) ($options['globalEnabled'] ?? true));
        $configuration->setMinimumNetAmount(50000);
        $this->entityManager->persist($configuration);

        $existing = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => self::PRODUCT_CODE]);
        if ($existing instanceof Product) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        $product = new Product();
        $product->setCode(self::PRODUCT_CODE);
        $product->setEnabled(true);
        $product->setLeasingEnabled((bool) ($options['productEnabled'] ?? true));
        $product->addChannel($channel);

        $variant = new ProductVariant();
        $variant->setCode('VARIANT_CODE_DIFFERENT_FROM_PRODUCT');
        $variant->setEnabled(true);
        $product->addVariant($variant);

        if ((bool) ($options['withPricing'] ?? true)) {
            $pricing = new ChannelPricing();
            $pricing->setChannelCode('CARDNEXT_DE');
            $pricing->setPrice((int) ($options['price'] ?? 110000));
            $variant->addChannelPricing($pricing);
        }

        $factor = $this->entityManager->getRepository(LeasingFactor::class)->findOneBy([
            'durationMonths' => 24,
            'active' => true,
        ]) ?? new LeasingFactor();
        $factor->setDurationMonths(24);
        $factor->setFactor('0.15000000');
        $factor->setActive((bool) ($options['factorActive'] ?? true));
        $product->addLeasingFactor($factor);

        $this->entityManager->persist($factor);
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    private function requestInquiry(string $code = self::PRODUCT_CODE): void
    {
        $this->client->request(
            'GET',
            sprintf('/leasing/anfrage/%s?duration=24', $code),
            server: ['HTTP_HOST' => 'www.cardnext.de'],
        );
    }
}
