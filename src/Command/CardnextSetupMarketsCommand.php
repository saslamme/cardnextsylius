<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Addressing\Country;
use App\Entity\Channel\Channel;
use App\Entity\Currency\Currency;
use App\Entity\Locale\Locale;
use App\Entity\Taxonomy\Taxon;
use App\International\CardnextMarketRegistry;
use App\International\MarketDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:setup-markets',
    description: 'Creates/updates the 13 Cardnext country channels, locales, currencies and countries.',
)]
final class CardnextSetupMarketsCommand extends Command
{
    // @phpstan-ignore missingType.generics
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CardnextMarketRegistry $markets,
        private readonly ChannelFactoryInterface $channelFactory,
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
            ->addOption(
                'enable-new',
                null,
                InputOption::VALUE_NONE,
                'Enable newly created non-DE channels. Without this option they are created disabled.',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $enableNew = (bool) $input->getOption('enable-new');

        $stats = [
            'locales_created' => 0,
            'currencies_created' => 0,
            'countries_created' => 0,
            'channels_created' => 0,
            'channels_updated' => 0,
        ];

        $rows = [];

        if ($dryRun) {
            $this->entityManager->beginTransaction();
        }

        try {
            $deChannel = $this->entityManager->getRepository(Channel::class)->findOneBy([
                'code' => 'CARDNEXT_DE',
            ]);

            $menuTaxon = $this->entityManager->getRepository(Taxon::class)->findOneBy([
                'code' => 'CARDNEXT_PRODUCTS',
            ]);

            foreach ($this->markets->all() as $market) {
                $locale = $this->getOrCreateLocale($market, $stats);
                $currency = $this->getOrCreateCurrency($market, $stats);
                $country = $this->getOrCreateCountry($market, $stats);

                /** @var Channel|null $channel */
                $channel = $this->entityManager->getRepository(Channel::class)->findOneBy([
                    'code' => $market->channelCode,
                ]);

                $created = false;
                $changed = false;

                if (!$channel instanceof Channel) {
                    $newChannel = $this->channelFactory->createNamed($market->name);

                    if (!$newChannel instanceof Channel) {
                        throw new \RuntimeException(sprintf(
                            'Sylius channel factory returned unexpected class "%s".',
                            get_debug_type($newChannel),
                        ));
                    }

                    $channel = $newChannel;
                    $channel->setCode($market->channelCode);
                    $channel->setEnabled(
                        $market->channelCode === 'CARDNEXT_DE' || $enableNew,
                    );
                    $this->entityManager->persist($channel);

                    $created = true;
                    ++$stats['channels_created'];
                }

                $changed = $this->setIfDifferent(
                    $channel->getName(),
                    $market->name,
                    fn () => $channel->setName($market->name),
                    // @phpstan-ignore booleanOr.rightAlwaysFalse
                ) || $changed;

                $changed = $this->setIfDifferent(
                    $channel->getHostname(),
                    $market->hostname,
                    fn () => $channel->setHostname($market->hostname),
                ) || $changed;

                if ($channel->getBaseCurrency() !== $currency) {
                    $channel->setBaseCurrency($currency);
                    $changed = true;
                }

                if ($channel->getDefaultLocale() !== $locale) {
                    $channel->setDefaultLocale($locale);
                    $changed = true;
                }

                if (!$channel->hasCurrency($currency)) {
                    $channel->addCurrency($currency);
                    $changed = true;
                }

                if (!$channel->hasLocale($locale)) {
                    $channel->addLocale($locale);
                    $changed = true;
                }

                if (!$channel->hasCountry($country)) {
                    $channel->addCountry($country);
                    $changed = true;
                }

                if ($menuTaxon instanceof Taxon && $channel->getMenuTaxon() !== $menuTaxon) {
                    $channel->setMenuTaxon($menuTaxon);
                    $changed = true;
                }

                if (
                    $channel->getContactEmail() === null ||
                    trim((string) $channel->getContactEmail()) === ''
                ) {
                    $contactEmail = $deChannel instanceof Channel &&
                        trim((string) $deChannel->getContactEmail()) !== ''
                        ? (string) $deChannel->getContactEmail()
                        : 'hello@cardnext.com';

                    $channel->setContactEmail($contactEmail);
                    $changed = true;
                }

                if (
                    $channel->getContactPhoneNumber() === null &&
                    $deChannel instanceof Channel &&
                    $deChannel->getContactPhoneNumber() !== null
                ) {
                    $channel->setContactPhoneNumber($deChannel->getContactPhoneNumber());
                    $changed = true;
                }

                // Existing non-DE channels are never disabled by this command.
                // --enable-new may also be used to enable already-created Cardnext markets.
                if (
                    $enableNew &&
                    $market->channelCode !== 'CARDNEXT_DE' &&
                    !$channel->isEnabled()
                ) {
                    $channel->setEnabled(true);
                    $changed = true;
                }

                if (!$created && $changed) {
                    ++$stats['channels_updated'];
                }

                $rows[] = [
                    $market->channelCode,
                    $market->hostname,
                    $market->localeCode,
                    $market->currencyCode,
                    $market->countryCode,
                    $channel->isEnabled() ? 'ja' : 'nein',
                    $created ? 'neu' : ($changed ? 'aktualisiert' : 'unverändert'),
                ];
            }

            $this->entityManager->flush();

            if ($dryRun) {
                $this->entityManager->rollback();
                $this->entityManager->clear();
            }
        } catch (\Throwable $exception) {
            if ($dryRun && $this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            $this->entityManager->clear();
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->table(
            ['Channel', 'Hostname', 'Locale', 'Währung', 'Land', 'Aktiv', 'Status'],
            $rows,
        );

        $io->table(
            ['Kennzahl', 'Wert'],
            [
                ['Locales neu', $stats['locales_created']],
                ['Währungen neu', $stats['currencies_created']],
                ['Länder neu', $stats['countries_created']],
                ['Channels neu', $stats['channels_created']],
                ['Channels aktualisiert', $stats['channels_updated']],
            ],
        );

        if ($dryRun) {
            $io->success('Dry-Run erfolgreich. Es wurden keine Änderungen gespeichert.');
        } else {
            $io->success(
                $enableNew
                    ? 'Cardnext-Märkte eingerichtet. Nicht-DE-Channels wurden aktiviert.'
                    : 'Cardnext-Märkte eingerichtet. Neue Nicht-DE-Channels bleiben bis zum Launch deaktiviert.',
            );
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, int> $stats
     */
    private function getOrCreateLocale(MarketDefinition $market, array &$stats): Locale
    {
        /** @var Locale|null $locale */
        $locale = $this->entityManager->getRepository(Locale::class)->findOneBy([
            'code' => $market->localeCode,
        ]);

        if ($locale instanceof Locale) {
            return $locale;
        }

        $locale = new Locale();
        $locale->setCode($market->localeCode);
        $this->entityManager->persist($locale);
        ++$stats['locales_created'];

        return $locale;
    }

    /**
     * @param array<string, int> $stats
     */
    private function getOrCreateCurrency(MarketDefinition $market, array &$stats): Currency
    {
        /** @var Currency|null $currency */
        $currency = $this->entityManager->getRepository(Currency::class)->findOneBy([
            'code' => $market->currencyCode,
        ]);

        if ($currency instanceof Currency) {
            return $currency;
        }

        $currency = new Currency();
        $currency->setCode($market->currencyCode);
        $this->entityManager->persist($currency);
        ++$stats['currencies_created'];

        return $currency;
    }

    /**
     * @param array<string, int> $stats
     */
    private function getOrCreateCountry(MarketDefinition $market, array &$stats): Country
    {
        /** @var Country|null $country */
        $country = $this->entityManager->getRepository(Country::class)->findOneBy([
            'code' => $market->countryCode,
        ]);

        if (!$country instanceof Country) {
            $country = new Country();
            $country->setCode($market->countryCode);
            $this->entityManager->persist($country);
            ++$stats['countries_created'];
        }

        if (!$country->isEnabled()) {
            $country->setEnabled(true);
        }

        return $country;
    }

    private function setIfDifferent(
        ?string $current,
        string $expected,
        callable $setter,
    ): bool {
        if ($current === $expected) {
            return false;
        }

        $setter();

        return true;
    }
}
