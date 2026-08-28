<?php

declare(strict_types=1);

namespace Tests\PrinterAdvisor;

use App\Entity\Product\PrinterAdvisorProfile;
use App\Entity\Product\Product;
use App\PrinterAdvisor\PrinterAdvisorAnswers;
use App\PrinterAdvisor\PrinterAdvisorCandidate;
use App\PrinterAdvisor\PrinterAdvisorRecommendationService;
use PHPUnit\Framework\TestCase;

final class PrinterAdvisorRecommendationServiceTest extends TestCase
{
    private PrinterAdvisorRecommendationService $service;

    protected function setUp(): void
    {
        $this->service = new PrinterAdvisorRecommendationService();
    }

    public function testMatchingVolumeWinsAndRankingIsDeterministic(): void
    {
        $matching = $this->candidate('MATCH', 0, 2000, 120000);
        $wrong = $this->candidate('WRONG', 10000, null, 120000);
        $answers = new PrinterAdvisorAnswers('500_2000', 'unsure', 'none', 'standard', 'secondary');
        self::assertSame(['MATCH', 'WRONG'], array_map(static fn ($r) => $r->product->getCode(), $this->service->recommend($answers, [$wrong, $matching])));
        self::assertEquals($this->service->recommend($answers, [$wrong, $matching]), $this->service->recommend($answers, [$wrong, $matching]));
    }

    /** @dataProvider hardRequirementProvider */
    public function testMissingHardRequirementIsExcluded(string $side, string $encoding, string $requirement): void
    {
        $answers = new PrinterAdvisorAnswers('under_500', $side, $encoding, $requirement, 'secondary');
        self::assertSame([], $this->service->recommend($answers, [$this->candidate('NO', 0, 500, 90000)]));
    }

    public static function hardRequirementProvider(): iterable
    {
        yield 'duplex' => ['duplex', 'none', 'standard'];
        yield 'RFID' => ['unsure', 'rfid_nfc', 'standard'];
        yield 'lamination' => ['unsure', 'none', 'lamination'];
    }

    public function testMatchingBudgetRanksHigherForEqualTechnology(): void
    {
        $answers = new PrinterAdvisorAnswers('under_500', 'unsure', 'none', 'standard', 'under_1000');
        $result = $this->service->recommend($answers, [$this->candidate('EXPENSIVE', 0, 500, 250000), $this->candidate('VALUE', 0, 500, 90000)]);
        self::assertSame('VALUE', $result[0]->product->getCode());
    }

    public function testDisabledProfileAndNoMatchReturnAnEmptyAdvisoryResult(): void
    {
        $candidate = $this->candidate('OFF', 0, 500, 90000);
        $candidate->profile->setEnabled(false);
        self::assertSame([], $this->service->recommend(new PrinterAdvisorAnswers('under_500', 'unsure', 'none', 'standard', 'secondary'), [$candidate]));
    }

    private function candidate(string $code, int $min, ?int $max, int $price): PrinterAdvisorCandidate
    {
        $product = new Product();
        $product->setCode($code);
        $profile = new PrinterAdvisorProfile();
        $profile->setProduct($product);
        $profile->setEnabled(true);
        $profile->setMinAnnualVolume($min);
        $profile->setMaxAnnualVolume($max);

        return new PrinterAdvisorCandidate($product, $profile, $price);
    }
}
