<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SessionLog\Services;

use App\Domain\SessionLog\Services\SessionLogImportService;
use App\Enums\SessionLogImportType;
use Tests\TestCase;

final class SessionLogImportServiceTest extends TestCase
{
    private SessionLogImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SessionLogImportService::class);
    }

    // --- Template Retrieval ---

    public function test_get_template_returns_rsm_template(): void
    {
        $template = $this->service->getTemplate(SessionLogImportType::RSM);

        $this->assertArrayHasKey('required_columns', $template);
        $this->assertArrayHasKey('optional_columns', $template);
        $this->assertArrayHasKey('column_mapping', $template);
        $this->assertContains('Entry_KEYID', $template['required_columns']);
        $this->assertContains('School', $template['required_columns']);
        $this->assertContains('STUDENTID', $template['required_columns']);
        $this->assertContains('Therapist Email', $template['required_columns']);
        $this->assertContains('Date of Service', $template['required_columns']);
        $this->assertContains('Service Name', $template['required_columns']);
        $this->assertContains('Hours', $template['required_columns']);
        $this->assertContains('Type1', $template['required_columns']);
        $this->assertContains('Therapy Start', $template['required_columns']);
        $this->assertContains('Therapy End', $template['required_columns']);
    }

    public function test_get_template_has_correct_column_mapping(): void
    {
        $template = $this->service->getTemplate(SessionLogImportType::RSM);
        $mapping = $template['column_mapping'];

        $this->assertEquals('reference_id', $mapping['Entry_KEYID']);
        $this->assertEquals('school_name', $mapping['School']);
        $this->assertEquals('student_id_number', $mapping['STUDENTID']);
        $this->assertEquals('therapist_email', $mapping['Therapist Email']);
        $this->assertEquals('session_date', $mapping['Date of Service']);
        $this->assertEquals('primary_service_name', $mapping['Service Name']);
        $this->assertEquals('hours', $mapping['Hours']);
        $this->assertEquals('type1', $mapping['Type1']);
        $this->assertEquals('start_time', $mapping['Therapy Start']);
        $this->assertEquals('end_time', $mapping['Therapy End']);
    }

    // --- Column Mapping ---

    public function test_map_columns_maps_csv_headers_to_internal_fields(): void
    {
        $template = $this->service->getTemplate(SessionLogImportType::RSM);
        $rowData = [
            'Entry_KEYID' => 'REF001',
            'School' => 'Test School',
            'STUDENTID' => 'STU001',
            'Therapist Email' => 'therapist@example.com',
            'Date of Service' => '3/15/2025',
            'Service Name' => 'Speech Therapy',
            'Hours' => '1.5',
            'Type1' => 'SA',
            'Therapy Start' => '9:00 AM',
            'Therapy End' => '10:30 AM',
            'Session Narrative' => 'Session notes here.',
            'Goals Progress' => 'Goals update here.',
            'Error' => 'N',
            'Error Details' => '',
            'Num Students' => '1',
        ];

        $mapped = $this->service->mapColumns($rowData, $template);

        $this->assertEquals('REF001', $mapped['reference_id']);
        $this->assertEquals('Test School', $mapped['school_name']);
        $this->assertEquals('STU001', $mapped['student_id_number']);
        $this->assertEquals('therapist@example.com', $mapped['therapist_email']);
        $this->assertEquals('3/15/2025', $mapped['session_date']);
        $this->assertEquals('Speech Therapy', $mapped['primary_service_name']);
        $this->assertEquals('1.5', $mapped['hours']);
        $this->assertEquals('SA', $mapped['type1']);
        $this->assertEquals('9:00 AM', $mapped['start_time']);
        $this->assertEquals('10:30 AM', $mapped['end_time']);
        $this->assertEquals('Session notes here.', $mapped['notes']);
        $this->assertEquals('Goals update here.', $mapped['goals_progress']);
        $this->assertEquals('N', $mapped['error_flag']);
        $this->assertEquals('1', $mapped['num_students']);
    }

    public function test_map_columns_handles_missing_optional_fields(): void
    {
        $template = $this->service->getTemplate(SessionLogImportType::RSM);
        $rowData = [
            'Entry_KEYID' => 'REF001',
            'School' => 'Test School',
        ];

        $mapped = $this->service->mapColumns($rowData, $template);

        $this->assertEquals('REF001', $mapped['reference_id']);
        $this->assertEquals('Test School', $mapped['school_name']);
        $this->assertArrayNotHasKey('therapist_email', $mapped);
    }

    public function test_map_columns_trims_whitespace(): void
    {
        $template = $this->service->getTemplate(SessionLogImportType::RSM);
        $rowData = [
            'Entry_KEYID' => '  REF001  ',
            'School' => '  Test School  ',
        ];

        $mapped = $this->service->mapColumns($rowData, $template);

        $this->assertEquals('REF001', $mapped['reference_id']);
        $this->assertEquals('Test School', $mapped['school_name']);
    }
}
