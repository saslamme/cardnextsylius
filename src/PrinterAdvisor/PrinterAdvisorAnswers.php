<?php

declare(strict_types=1);

namespace App\PrinterAdvisor;

final readonly class PrinterAdvisorAnswers
{
    public const VOLUMES = ['under_500', '500_2000', '2000_10000', 'over_10000'];

    public const SIDES = ['single', 'duplex', 'unsure'];

    public const ENCODINGS = ['none', 'magnetic', 'contact_chip', 'rfid_nfc'];

    public const REQUIREMENTS = ['standard', 'durability', 'lamination', 'retransfer', 'speed'];

    public const BUDGETS = ['under_1000', '1000_2000', '2000_4000', 'secondary'];

    public function __construct(
        public string $volume,
        public string $sides,
        public string $encoding,
        public string $requirement,
        public string $budget,
    ) {
        self::assertChoice($volume, self::VOLUMES, 'volume');
        self::assertChoice($sides, self::SIDES, 'sides');
        self::assertChoice($encoding, self::ENCODINGS, 'encoding');
        self::assertChoice($requirement, self::REQUIREMENTS, 'requirement');
        self::assertChoice($budget, self::BUDGETS, 'budget');
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        return new self(...array_map(static fn (string $key): string => is_string($input[$key] ?? null) ? $input[$key] : '', ['volume', 'sides', 'encoding', 'requirement', 'budget']));
    }

    public function representativeVolume(): int
    {
        return match ($this->volume) {
            'under_500' => 250, '500_2000' => 1250, '2000_10000' => 6000, default => 12000
        };
    }

    /** @return array{0: int, 1: int}|null Prices in cents. */
    public function budgetRange(): ?array
    {
        return match ($this->budget) {
            'under_1000' => [0, 100000], '1000_2000' => [100000, 200000], '2000_4000' => [200000, 400000], default => null
        };
    }

    /** @param list<string> $choices */
    private static function assertChoice(string $value, array $choices, string $field): void
    {
        if (!in_array($value, $choices, true)) {
            throw new \InvalidArgumentException(sprintf('Ungültige Auswahl für „%s“.', $field));
        }
    }
}
