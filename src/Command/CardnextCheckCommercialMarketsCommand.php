<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Addressing\Zone;
use App\Entity\Channel\Channel;
use App\Entity\Payment\PaymentMethod;
use App\Entity\Shipping\ShippingMethod;
use App\International\CardnextMarketRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:check-commercial-markets',
    description: 'Checks commercial readiness of all Cardnext channels.',
)]
final class CardnextCheckCommercialMarketsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CardnextMarketRegistry $markets,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var PaymentMethod|null $vorkasse */
        $vorkasse = $this->entityManager->getRepository(PaymentMethod::class)->findOneBy([
            'code' => 'VORKASSE',
        ]);

        $rows = [];
        $hardFailures = 0;

        foreach ($this->markets->all() as $market) {
            /** @var Channel|null $channel */
            $channel = $this->entityManager->getRepository(Channel::class)->findOneBy([
                'code' => $market->channelCode,
            ]);

            $shippingZoneCode = $market->countryCode . '_SHIPPING';
            $taxZoneCode = $market->countryCode . '_TAX';
            $shippingMethodCode = $market->countryCode . '_STANDARD';

            /** @var Zone|null $shippingZone */
            $shippingZone = $this->entityManager->getRepository(Zone::class)->findOneBy([
                'code' => $shippingZoneCode,
            ]);

            /** @var Zone|null $taxZone */
            $taxZone = $this->entityManager->getRepository(Zone::class)->findOneBy([
                'code' => $taxZoneCode,
            ]);

            /** @var ShippingMethod|null $shippingMethod */
            $shippingMethod = $this->entityManager->getRepository(ShippingMethod::class)->findOneBy([
                'code' => $shippingMethodCode,
            ]);

            if (!$channel instanceof Channel || !$shippingZone instanceof Zone || !$taxZone instanceof Zone) {
                ++$hardFailures;
            }

            $amount = null;
            if ($shippingMethod instanceof ShippingMethod && $channel instanceof Channel) {
                // @phpstan-ignore offsetAccess.nonOffsetAccessible
                $amount = $shippingMethod->getConfiguration()[$market->channelCode]['amount'] ?? null;
            }

            $paymentAssigned = $vorkasse instanceof PaymentMethod &&
                $channel instanceof Channel &&
                $vorkasse->hasChannel($channel);

            $taxStatus = 'offen';
            if ($channel instanceof Channel && $channel->getDefaultTaxZone() !== null) {
                $taxStatus = (string) $channel->getDefaultTaxZone()->getCode();
            }

            $rows[] = [
                $market->channelCode,
                $shippingZone instanceof Zone ? 'OK' : 'FEHLT',
                $taxZone instanceof Zone ? 'OK' : 'FEHLT',
                $shippingMethod instanceof ShippingMethod ? 'OK' : 'FEHLT',
                // @phpstan-ignore cast.string
                $amount === null ? 'offen' : (string) $amount,
                $shippingMethod?->isEnabled() ? 'ja' : 'nein',
                $paymentAssigned ? 'OK' : 'FEHLT',
                $taxStatus,
                $channel?->isEnabled() ? 'ja' : 'nein',
            ];
        }

        $io->table(
            [
                'Channel',
                'Versandzone',
                'Tax-Zone',
                'Versandart',
                'Preis minor',
                'Versand aktiv',
                'Vorkasse',
                'Default Tax',
                'Channel aktiv',
            ],
            $rows,
        );

        $io->note(
            'Preis 0 bei einer deaktivierten Versandart bedeutet bewusst: Versandpreis noch pflegen. '
            . 'Internationale Steuersätze werden erst in der Steuerphase festgelegt.',
        );

        return $hardFailures > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
