<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Addressing\Zone;
use App\Entity\Addressing\ZoneMember;
use App\Entity\Channel\Channel;
use App\Entity\Payment\PaymentMethod;
use App\Entity\Shipping\ShippingMethod;
use App\International\CardnextMarketRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Addressing\Factory\ZoneFactoryInterface;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Shipping\Calculator\DefaultCalculators;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:setup-commercial-markets',
    description: 'Prepares shipping/tax zones, standard shipping placeholders and Vorkasse channel assignments for all Cardnext markets.',
)]
final class CardnextSetupCommercialMarketsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CardnextMarketRegistry $markets,
        private readonly ZoneFactoryInterface $zoneFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Validate and show changes without committing them.',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $this->entityManager->beginTransaction();
        }

        try {
            /** @var PaymentMethod|null $vorkasse */
            $vorkasse = $this->entityManager->getRepository(PaymentMethod::class)->findOneBy([
                'code' => 'VORKASSE',
            ]);

            if (!$vorkasse instanceof PaymentMethod) {
                throw new \RuntimeException(
                    'Payment method "VORKASSE" was not found. Phase 17B does not create gateways automatically.',
                );
            }

            $rows = [];

            foreach ($this->markets->all() as $market) {
                /** @var Channel|null $channel */
                $channel = $this->entityManager->getRepository(Channel::class)->findOneBy([
                    'code' => $market->channelCode,
                ]);

                if (!$channel instanceof Channel) {
                    throw new \RuntimeException(sprintf(
                        'Channel "%s" is missing. Run Phase 17A first.',
                        $market->channelCode,
                    ));
                }

                $shippingZoneCode = $market->countryCode . '_SHIPPING';
                $taxZoneCode = $market->countryCode . '_TAX';
                $shippingMethodCode = $market->countryCode . '_STANDARD';

                $shippingZone = $this->getOrCreateCountryZone(
                    $shippingZoneCode,
                    $market->countryCode . ' Shipping',
                    'shipping',
                    $market->countryCode,
                );

                $taxZone = $this->getOrCreateCountryZone(
                    $taxZoneCode,
                    $market->countryCode . ' Tax',
                    'tax',
                    $market->countryCode,
                );

                // Germany currently has a live tax configuration on the legacy zone "DE".
                // Do not replace it automatically until the tax rate is explicitly migrated.
                if ($market->channelCode !== 'CARDNEXT_DE') {
                    if ($channel->getDefaultTaxZone() !== $taxZone) {
                        $channel->setDefaultTaxZone($taxZone);
                    }
                }

                if (!$vorkasse->hasChannel($channel)) {
                    $vorkasse->addChannel($channel);
                }

                /** @var ShippingMethod|null $shippingMethod */
                $shippingMethod = $this->entityManager->getRepository(ShippingMethod::class)->findOneBy([
                    'code' => $shippingMethodCode,
                ]);

                $shippingCreated = false;

                if (!$shippingMethod instanceof ShippingMethod) {
                    $shippingMethod = new ShippingMethod();
                    $shippingMethod->setCode($shippingMethodCode);
                    $shippingMethod->setEnabled(false);
                    $shippingMethod->setPosition(0);
                    $shippingMethod->setCalculator(DefaultCalculators::FLAT_RATE);
                    $shippingMethod->setConfiguration([
                        $market->channelCode => ['amount' => 0],
                    ]);
                    $shippingMethod->setZone($shippingZone);
                    $shippingMethod->addChannel($channel);

                    $shippingMethod->setCurrentLocale($market->localeCode);
                    $shippingMethod->setFallbackLocale($market->localeCode);
                    $shippingMethod->setName('Standardversand');
                    $shippingMethod->setDescription(
                        'Preis vor Aktivierung des Marktes konfigurieren.',
                    );

                    $this->entityManager->persist($shippingMethod);
                    $shippingCreated = true;
                } else {
                    if ($shippingMethod->getZone() !== $shippingZone) {
                        $shippingMethod->setZone($shippingZone);
                    }

                    if (!$shippingMethod->hasChannel($channel)) {
                        $shippingMethod->addChannel($channel);
                    }

                    $configuration = $shippingMethod->getConfiguration();

                    if (!array_key_exists($market->channelCode, $configuration)) {
                        $configuration[$market->channelCode] = ['amount' => 0];
                        $shippingMethod->setConfiguration($configuration);
                    }

                    if ($shippingMethod->getCalculator() === null) {
                        $shippingMethod->setCalculator(DefaultCalculators::FLAT_RATE);
                    }
                }

                // Preserve existing German shipping settings, including the live €5.90 configuration.
                // Newly created placeholders stay disabled until a real market shipping price is entered.
                if ($market->channelCode === 'CARDNEXT_DE' && !$shippingCreated) {
                    // no-op
                } elseif ($shippingCreated) {
                    $shippingMethod->setEnabled(false);
                }

                $amount = $shippingMethod->getConfiguration()[$market->channelCode]['amount'] ?? null;

                $rows[] = [
                    $market->channelCode,
                    $shippingZoneCode,
                    $taxZoneCode,
                    $shippingMethodCode,
                    $amount !== null ? (string) $amount : 'offen',
                    $shippingMethod->isEnabled() ? 'ja' : 'nein',
                    $vorkasse->hasChannel($channel) ? 'ja' : 'nein',
                    $channel->getDefaultTaxZone()?->getCode() ?? 'offen',
                ];
            }

            $this->entityManager->flush();

            if ($dryRun) {
                $this->entityManager->rollback();
                $this->entityManager->clear();
            }

            $io->table(
                [
                    'Channel',
                    'Versandzone',
                    'Steuerzone',
                    'Versandart',
                    'Versand (minor)',
                    'Versand aktiv',
                    'Vorkasse',
                    'Default Tax Zone',
                ],
                $rows,
            );

            $io->note([
                'Neue Versandarten werden bewusst deaktiviert mit Betrag 0 angelegt.',
                'Vor Aktivierung eines Landes muss der echte Versandpreis gepflegt werden.',
                'Phase 17B legt keine internationalen Steuersätze an.',
                'Der bestehende deutsche Steuer-Setup wird nicht automatisch umgehängt.',
            ]);

            $io->success(
                $dryRun
                    ? 'Dry-Run erfolgreich. Es wurden keine Änderungen gespeichert.'
                    : 'Kommerzielle Markt-Grundstruktur wurde vorbereitet.',
            );

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            if ($dryRun && $this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            $this->entityManager->clear();
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function getOrCreateCountryZone(
        string $code,
        string $name,
        string $scope,
        string $countryCode,
    ): Zone {
        /** @var Zone|null $zone */
        $zone = $this->entityManager->getRepository(Zone::class)->findOneBy([
            'code' => $code,
        ]);

        if (!$zone instanceof Zone) {
            $newZone = $this->zoneFactory->createWithMembers([$countryCode]);

            if (!$newZone instanceof Zone) {
                throw new \RuntimeException(sprintf(
                    'Sylius zone factory returned unexpected class "%s".',
                    get_debug_type($newZone),
                ));
            }

            $zone = $newZone;
            $zone->setCode($code);
            $zone->setName($name);
            $zone->setType(ZoneInterface::TYPE_COUNTRY);
            $zone->setScope($scope);
            $zone->setPriority(0);

            $this->entityManager->persist($zone);

            return $zone;
        }

        $zone->setName($name);
        $zone->setType(ZoneInterface::TYPE_COUNTRY);
        $zone->setScope($scope);

        $hasCountry = false;
        foreach ($zone->getMembers() as $member) {
            if ($member->getCode() === $countryCode) {
                $hasCountry = true;
                break;
            }
        }

        if (!$hasCountry) {
            $member = new ZoneMember();
            $member->setCode($countryCode);
            $zone->addMember($member);
        }

        return $zone;
    }
}
