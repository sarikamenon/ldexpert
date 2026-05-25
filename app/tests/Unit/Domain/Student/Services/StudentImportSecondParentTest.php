<?php

declare(strict_types=1);

use App\Domain\Student\Services\StudentImportService;
use App\DTOs\ImportStudentDTO;
use App\Enums\StudentImportType;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = $this->app->make(StudentImportService::class);
    User::factory()->admin()->create();
    $this->school = School::factory()->create(['external_emr_name' => 'Test School EMR']);
});

it('maps the second parent columns for the NOVA template', function () {
    $template = $this->service->getTemplate(StudentImportType::NOVA);

    expect($template['column_mapping'])
        ->toHaveKey('parent_guardian_2_name', 'parent_guardian_2_name')
        ->toHaveKey('parent_guardian_2_email', 'parent_guardian_2_email')
        ->toHaveKey('parent_guardian_2_phone', 'parent_guardian_2_phone');

    $mapped = $this->service->mapColumns([
        'first_name' => 'Ava',
        'last_name' => 'Smith',
        'parent_guardian_2_name' => 'John Smith',
        'parent_guardian_2_email' => 'john@example.com',
        'parent_guardian_2_phone' => '987-654-3210',
    ], $template);

    expect($mapped['parent_guardian_2_name'])->toBe('John Smith')
        ->and($mapped['parent_guardian_2_email'])->toBe('john@example.com')
        ->and($mapped['parent_guardian_2_phone'])->toBe('987-654-3210');
});

it('maps Parent Contact 2 to the second guardian fields for TutorBird', function () {
    $template = $this->service->getTemplate(StudentImportType::TUTORBIRD);

    $mapped = $this->service->mapColumns([
        'First Name' => 'Emma',
        'Last Name' => 'Watson',
        'Parent Contact 1 First Name' => 'Helen',
        'Parent Contact 1 Last Name' => 'Watson',
        'Parent Contact 1 Email' => 'helen@example.com',
        'Parent Contact 1 Mobile Phone' => '210-555-1234',
        'Parent Contact 2 First Name' => 'Chris',
        'Parent Contact 2 Last Name' => 'Watson',
        'Parent Contact 2 Email' => 'chris@example.com',
        'Parent Contact 2 Mobile Phone' => '210-555-9876',
    ], $template);

    expect($mapped['parent_guardian_2_first_name'])->toBe('Chris')
        ->and($mapped['parent_guardian_2_last_name'])->toBe('Watson')
        ->and($mapped['parent_guardian_2_email'])->toBe('chris@example.com')
        ->and($mapped['parent_guardian_2_phone'])->toBe('210-555-9876');
});

it('defines a combine transformation for the second guardian name in TutorBird', function () {
    $template = $this->service->getTemplate(StudentImportType::TUTORBIRD);

    $targets = collect($template['transformations'])->pluck('target');

    expect($targets)->toContain('parent_guardian_name')
        ->and($targets)->toContain('parent_guardian_2_name');
});

it('rejects an invalid second parent email during row validation', function () {
    $dto = ImportStudentDTO::fromArray([
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'test@example.com',
        'id_number' => 'STU-SP',
        'timezone' => 'America/New_York',
        'parent_guardian_2_email' => 'not-an-email',
    ], 1);

    $errors = $this->service->validateRow($dto, $this->school->id);

    expect($errors)->not->toBeEmpty();
});
