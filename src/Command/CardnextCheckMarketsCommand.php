<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Channel\Channel;
use App\International\CardnextMarketRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:check-markets',
    description: 'Shows the current Cardnext market/channel readiness.',
)]
final class CardnextCheckMarketsCommand extends Command
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
        $rows = [];
        $missing = 0;

        foreach ($this->markets->all() as $market) {
            /** @var Channel|null $channel */
            $channel = $this->entityManager->getRepository(Channel::class)->findOneBy([
                'code' => $market->channelCode,
            ]);

            if (!$channel instanceof Channel) {
                ++$missing;
                $rows[] = [
                    $market->channelCode,
                    'FEHLT',
                    $market->hostname,
                    $market->localeCode,
                    $market->currencyCode,
                    $market->countryCode,
                    '–',
                    '–',
                ];

                continue;
            }

            $locale = $channel->getDefaultLocale()?->getCode() ?? '–';
            $currency = $channel->getBaseCurrency()?->getCode() ?? '–';
            $countryOk = false;

            foreach ($channel->getCountries() as $country) {
                if ($country->getCode() === $market->countryCode) {
                    $countryOk = true;
                    break;
                }
            }

            $coreOk = $channel->getHostname() === $market->hostname
                && $locale === $market->localeCode
                && $currency === $market->currencyCode
                && $countryOk;

            $rows[] = [
                $market->channelCode,
                $coreOk ? 'OK' : 'PRÜFEN',
                (string) $channel->getHostname(),
                $locale,
                $currency,
                $countryOk ? $market->countryCode : 'FEHLT',
                $channel->isEnabled() ? 'ja' : 'nein',
                $channel->getDefaultTaxZone()?->getCode() ?? 'offen',
            ];
        }

        $io->table(
            ['Channel', 'Core', 'Hostname', 'Locale', 'Währung', 'Land', 'Aktiv', 'Steuerzone'],
            $rows,
        );

        $io->note(
            'Eine offene Steuerzone ist in Phase 17A bei neuen Märkten beabsichtigt. '
            . 'Steuern, Versand, Zahlarten und Marktpreise werden anschließend separat konfiguriert.',
        );

        return $missing > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
