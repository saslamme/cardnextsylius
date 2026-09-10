<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Maintenance\WertgarantieVariantResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cardnext:wertgarantie:setup', description: 'Creates or updates the WERTGARANTIE KOMFORT 3 and KOMFORT 5 add-ons.')]
final class CardnextSetupWertgarantieCommand extends Command
{
    private const LOCALE = 'de_DE';

    /** @var array<string, array{name:string,short:string,prefix:string,prices:list<int>}> */
    private const TARIFFS = [
        WertgarantieVariantResolver::KOMFORT_3 => ['name' => 'WERTGARANTIE Komfort 3', 'short' => '3 Jahre Geräteschutz', 'prefix' => 'WERTGARANTIE_K3_', 'prices' => WertgarantieVariantResolver::PRICES[WertgarantieVariantResolver::KOMFORT_3]],
        WertgarantieVariantResolver::KOMFORT_5 => ['name' => 'WERTGARANTIE Komfort 5', 'short' => '5 Jahre Geräteschutz', 'prefix' => 'WERTGARANTIE_K5_', 'prices' => WertgarantieVariantResolver::PRICES[WertgarantieVariantResolver::KOMFORT_5]],
    ];

    private const DESCRIPTION = "Volle Reparaturkostenübernahme bei Defekten\nFall- und Sturzschäden\nBedienfehler\nWasserschäden\nElektronikschäden\nZubehör im Lieferumfang mit abgedeckt\nBei Totalschaden Gerät gleicher Art und Güte\nWeltweiter Schutz\nPrivate und berufliche Nutzung";

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $channels = array_values(array_filter(
            $this->entityManager->getRepository(Channel::class)->findAll(),
            static fn (Channel $channel): bool => $channel->isEnabled() && $channel->getBaseCurrency()?->getCode() === 'EUR',
        ));
        if ($channels === []) {
            $io->error('Kein aktivierter Verkaufskanal mit EUR als Basiswährung gefunden. Es wurden keine Tarife angelegt.');

            return Command::FAILURE;
        }

        foreach (self::TARIFFS as $productCode => $tariff) {
            $product = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $productCode]);
            if (!$product instanceof Product) {
                $product = new Product();
                $product->setCode($productCode);
                $this->entityManager->persist($product);
            }
            $product->setEnabled(true);
            $product->setAddonOnly(true);
            $product->setCurrentLocale(self::LOCALE);
            $product->setFallbackLocale(self::LOCALE);
            $product->setName($tariff['name']);
            $product->setSlug(strtolower(str_replace('_', '-', $productCode)));
            $product->setShortDescription($tariff['short']);
            $product->setDescription(self::DESCRIPTION);
            foreach ($channels as $channel) {
                $product->addChannel($channel);
            }

            foreach (WertgarantieVariantResolver::LIMITS as $index => $limit) {
                $suffix = sprintf($limit === 1000000 ? '%d' : '%04d', intdiv($limit, 100));
                $variantCode = $tariff['prefix'] . $suffix;
                $variant = $this->entityManager->getRepository(ProductVariant::class)->findOneBy(['code' => $variantCode]);
                if (!$variant instanceof ProductVariant) {
                    $variant = new ProductVariant();
                    $variant->setCode($variantCode);
                    $product->addVariant($variant);
                    $this->entityManager->persist($variant);
                } elseif ($variant->getProduct() !== $product) {
                    throw new \RuntimeException(sprintf('Variante "%s" gehört bereits zu einem anderen Produkt.', $variantCode));
                }
                $variant->setEnabled(true);
                $variant->setCurrentLocale(self::LOCALE);
                $variant->setFallbackLocale(self::LOCALE);
                $variant->setName(sprintf('Bis %s EUR', number_format($limit / 100, 0, ',', '.')));
                foreach ($channels as $channel) {
                    $pricing = $variant->getChannelPricingForChannel($channel);
                    if ($pricing === null) {
                        $pricing = new ChannelPricing();
                        $pricing->setChannelCode((string) $channel->getCode());
                        $variant->addChannelPricing($pricing);
                    }
                    $pricing->setPrice($tariff['prices'][$index]);
                }
            }
        }

        $this->entityManager->flush();
        $io->success('KOMFORT 3 und KOMFORT 5 mit je 14 EUR-Preisvarianten wurden eingerichtet. Produktzuordnungen wurden nicht verändert.');

        return Command::SUCCESS;
    }
}
