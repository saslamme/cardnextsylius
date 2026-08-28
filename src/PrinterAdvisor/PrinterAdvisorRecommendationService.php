<?php

declare(strict_types=1);

namespace App\PrinterAdvisor;

final class PrinterAdvisorRecommendationService
{
    /**
     * @param list<PrinterAdvisorCandidate> $candidates
     *
     * @return list<PrinterAdvisorRecommendation>
     */
    public function recommend(PrinterAdvisorAnswers $answers, array $candidates): array
    {
        $ranked = [];
        foreach ($candidates as $candidate) {
            $profile = $candidate->profile;
            if (!$profile->isEnabled() || !$this->meetsHardRequirements($answers, $profile)) {
                continue;
            }

            $score = 1000 + $profile->getPriority();
            $reasons = [];
            $volume = $answers->representativeVolume();
            if ($volume >= $profile->getMinAnnualVolume() && ($profile->getMaxAnnualVolume() === null || $volume <= $profile->getMaxAnnualVolume())) {
                $score += 500;
                $reasons[] = 'für Ihr jährliches Druckvolumen ausgelegt ist';
            } else {
                $distance = $volume < $profile->getMinAnnualVolume() ? $profile->getMinAnnualVolume() - $volume : $volume - (int) $profile->getMaxAnnualVolume();
                $score -= min(450, (int) round(450 * $distance / max(1, $volume)));
            }

            if ($answers->sides === 'duplex') {
                $score += 160;
                $reasons[] = 'automatischen Duplexdruck unterstützt';
            }
            if ($answers->sides === 'single') {
                $reasons[] = 'einseitige Karten zuverlässig druckt';
            }
            $encodingReason = match ($answers->encoding) {
                'magnetic' => 'Magnetstreifen kodieren kann', 'contact_chip' => 'Kontaktchip-Kodierung unterstützt', 'rfid_nfc' => 'RFID-/NFC-Kodierung unterstützt', default => null
            };
            if ($encodingReason !== null) {
                $score += 180;
                $reasons[] = $encodingReason;
            }
            $requirementReason = match ($answers->requirement) {
                'durability' => 'für besonders haltbare Karten geeignet ist', 'lamination' => 'eine Laminierung ermöglicht',
                'retransfer' => 'hochwertigen randlosen Retransferdruck bietet', 'speed' => 'eine hohe Druckleistung bietet',
                default => 'für den Standard-Kartendruck geeignet ist',
            };
            $score += $answers->requirement === 'speed' ? $profile->getPerformanceClass() * 35 : 100;
            $reasons[] = $requirementReason;

            $budget = $answers->budgetRange();
            if ($budget !== null) {
                if ($candidate->price >= $budget[0] && $candidate->price <= $budget[1]) {
                    $score += 250;
                    $reasons[] = 'in Ihrem Investitionsrahmen liegt';
                } elseif ($candidate->price > $budget[1]) {
                    $score -= min(300, (int) (($candidate->price - $budget[1]) / 1000));
                } else {
                    $score += 100;
                    $reasons[] = 'Ihr Budget schont';
                }
            }

            $ranked[] = new PrinterAdvisorRecommendation($candidate->product, $candidate->price, $score, array_slice(array_unique($reasons), 0, 4), '');
        }

        usort($ranked, static fn (PrinterAdvisorRecommendation $a, PrinterAdvisorRecommendation $b): int => [$b->score, $a->price, $a->product->getCode()] <=> [$a->score, $b->price, $b->product->getCode()]);
        $labels = ['Beste Empfehlung', 'Preis-Leistungs-Alternative', 'Profi-Alternative'];

        return array_map(static fn (PrinterAdvisorRecommendation $item, int $index): PrinterAdvisorRecommendation => new PrinterAdvisorRecommendation($item->product, $item->price, $item->score, $item->reasons, $labels[$index]), array_slice($ranked, 0, 3), array_keys(array_slice($ranked, 0, 3)));
    }

    private function meetsHardRequirements(PrinterAdvisorAnswers $a, \App\Entity\Product\PrinterAdvisorProfile $p): bool
    {
        return !($a->sides === 'single' && !$p->isSingleSided()) &&
            !($a->sides === 'duplex' && !$p->isDuplex()) &&
            !($a->encoding === 'magnetic' && !$p->hasMagneticStripe()) &&
            !($a->encoding === 'contact_chip' && !$p->hasContactChip()) &&
            !($a->encoding === 'rfid_nfc' && !$p->hasRfidNfc()) &&
            !($a->requirement === 'standard' && !$p->isDirectPrinting()) &&
            !($a->requirement === 'durability' && !$p->hasHighDurability()) &&
            !($a->requirement === 'lamination' && !$p->hasLamination()) &&
            !($a->requirement === 'retransfer' && !$p->isRetransfer());
    }
}
