<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SessionLog\Services;

use App\Domain\SessionLog\Services\SessionLogImportRowProcessor;
use App\Enums\SessionOutcome;
use Tests\TestCase;

final class SessionLogImportRowProcessorTest extends TestCase
{
    private SessionLogImportRowProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = app(SessionLogImportRowProcessor::class);
    }

    // --- Outcome Mapping ---

    public function test_map_outcome_sa(): void
    {
        $this->assertEquals(SessionOutcome::SERVICES_ADMINISTERED, $this->processor->mapOutcome('SA'));
    }

    public function test_map_outcome_ns(): void
    {
        $this->assertEquals(SessionOutcome::NO_SHOW, $this->processor->mapOutcome('NS'));
    }

    public function test_map_outcome_bc(): void
    {
        $this->assertEquals(SessionOutcome::BILLABLE_CANCELLATION, $this->processor->mapOutcome('BC'));
    }

    public function test_map_outcome_nbc1(): void
    {
        $this->assertEquals(SessionOutcome::NON_BILLABLE_CANCELLATION_CLIENT, $this->processor->mapOutcome('NBC1'));
    }

    public function test_map_outcome_nbc2(): void
    {
        $this->assertEquals(SessionOutcome::NON_BILLABLE_CANCELLATION_PROVIDER, $this->processor->mapOutcome('NBC2'));
    }

    public function test_map_outcome_case_insensitive(): void
    {
        $this->assertEquals(SessionOutcome::SERVICES_ADMINISTERED, $this->processor->mapOutcome('sa'));
        $this->assertEquals(SessionOutcome::NO_SHOW, $this->processor->mapOutcome('ns'));
        $this->assertEquals(SessionOutcome::BILLABLE_CANCELLATION, $this->processor->mapOutcome(' Bc '));
    }

    public function test_map_outcome_unknown_returns_null(): void
    {
        $this->assertNull($this->processor->mapOutcome('UNKNOWN'));
        $this->assertNull($this->processor->mapOutcome(''));
        $this->assertNull($this->processor->mapOutcome('XYZ'));
    }

    // --- Notes Building ---

    public function test_build_notes_narrative_only(): void
    {
        $result = $this->processor->buildNotes('Session went well.', '');
        $this->assertEquals('Session went well.', $result);
    }

    public function test_build_notes_goals_only(): void
    {
        $result = $this->processor->buildNotes('', 'Student progressed well.');
        $this->assertEquals('Student progressed well.', $result);
    }

    public function test_build_notes_both_present(): void
    {
        $result = $this->processor->buildNotes('Session went well.', 'Student progressed.');
        $expected = "Session went well.\n\n--- Goals Progress ---\nStudent progressed.";
        $this->assertEquals($expected, $result);
    }

    public function test_build_notes_both_empty(): void
    {
        $result = $this->processor->buildNotes('', '');
        $this->assertEquals('', $result);
    }

    public function test_build_notes_trims_whitespace(): void
    {
        $result = $this->processor->buildNotes('  Session notes  ', '  Goals  ');
        $expected = "Session notes\n\n--- Goals Progress ---\nGoals";
        $this->assertEquals($expected, $result);
    }

    // --- Date Parsing ---

    public function test_parse_date_m_d_y_format(): void
    {
        $this->assertEquals('2025-03-15', $this->processor->parseDate('3/15/2025'));
    }

    public function test_parse_date_mm_dd_yyyy_format(): void
    {
        $this->assertEquals('2025-01-05', $this->processor->parseDate('01/05/2025'));
    }

    public function test_parse_date_iso_format_fallback(): void
    {
        $this->assertEquals('2025-03-15', $this->processor->parseDate('2025-03-15'));
    }

    public function test_parse_date_invalid_returns_null(): void
    {
        $this->assertNull($this->processor->parseDate('invalid'));
    }

    public function test_parse_date_empty_returns_today(): void
    {
        // Carbon::parse('') falls back to current date
        $this->assertNotNull($this->processor->parseDate(''));
    }

    // --- Time Parsing ---

    public function test_parse_time_12_hour_format(): void
    {
        $this->assertEquals('09:30:00', $this->processor->parseTime('9:30 AM'));
        $this->assertEquals('14:00:00', $this->processor->parseTime('2:00 PM'));
    }

    public function test_parse_time_24_hour_fallback(): void
    {
        $this->assertEquals('14:30:00', $this->processor->parseTime('14:30'));
    }

    public function test_parse_time_invalid_returns_null(): void
    {
        $this->assertNull($this->processor->parseTime('invalid'));
    }

    public function test_parse_time_empty_returns_value(): void
    {
        // Carbon::parse('') falls back to current time
        $this->assertNotNull($this->processor->parseTime(''));
    }
}
