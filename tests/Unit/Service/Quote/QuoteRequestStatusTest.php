<?php

declare(strict_types=1);
namespace App\Tests\Unit\Service\Quote; use App\Enum\Quote\QuoteRequestStatus; use PHPUnit\Framework\TestCase;
final class QuoteRequestStatusTest extends TestCase {public function testPhaseOneTransitionsAreCentralised():void{self::assertTrue(QuoteRequestStatus::New->canTransitionTo(QuoteRequestStatus::InProgress));self::assertTrue(QuoteRequestStatus::InProgress->canTransitionTo(QuoteRequestStatus::Question));self::assertTrue(QuoteRequestStatus::Question->canTransitionTo(QuoteRequestStatus::InProgress));self::assertTrue(QuoteRequestStatus::Question->canTransitionTo(QuoteRequestStatus::Closed));self::assertFalse(QuoteRequestStatus::Closed->canTransitionTo(QuoteRequestStatus::New));} public function testAllPersistedValuesAreExposed():void{self::assertSame(['new','in_progress','question','closed'],QuoteRequestStatus::values());}}
