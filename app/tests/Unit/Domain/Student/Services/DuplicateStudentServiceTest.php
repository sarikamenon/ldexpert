<?php

declare(strict_types=1);

use App\Domain\Student\Services\DuplicateStudentService;
use App\DTOs\Student\Duplicate\DuplicateCandidateDTO;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeStudent(array $profile = [], array $user = []): StudentProfile
{
    $owner = User::factory()->student()->create($user);

    return StudentProfile::factory()->create(array_merge([
        'user_id' => $owner->id,
    ], $profile));
}

function candidate(array $overrides = []): DuplicateCandidateDTO
{
    return DuplicateCandidateDTO::fromArray(array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => null,
        'school_id' => null,
        'date_of_birth' => null,
        'grade_level' => null,
    ], $overrides));
}

it('flags an existing student when first and last name both match', function () {
    makeStudent(['first_name' => 'Jane', 'last_name' => 'Smith']);

    $matches = app(DuplicateStudentService::class)->findMatches(candidate());

    expect($matches)->toHaveCount(1)
        ->and($matches->first()->name)->toBe('Jane Smith');
});

it('matches case-insensitively', function () {
    makeStudent(['first_name' => 'jane', 'last_name' => 'SMITH']);

    $matches = app(DuplicateStudentService::class)->findMatches(
        candidate(['first_name' => 'JANE', 'last_name' => 'smith'])
    );

    expect($matches)->toHaveCount(1);
});

it('matches with accents folded (José = Jose)', function () {
    makeStudent(['first_name' => 'José', 'last_name' => 'García']);

    $matches = app(DuplicateStudentService::class)->findMatches(
        candidate(['first_name' => 'Jose', 'last_name' => 'Garcia'])
    );

    expect($matches)->toHaveCount(1);
});

it('returns nothing when only the last name matches', function () {
    makeStudent(['first_name' => 'Bob', 'last_name' => 'Smith']);

    $matches = app(DuplicateStudentService::class)->findMatches(candidate());

    expect($matches)->toBeEmpty();
});

it('does not flag a sibling: same parent email but different name', function () {
    makeStudent(
        ['first_name' => 'Bob', 'last_name' => 'Smith'],
        ['email' => 'parent@example.com'],
    );

    $matches = app(DuplicateStudentService::class)->findMatches(
        candidate(['email' => 'parent@example.com'])
    );

    expect($matches)->toBeEmpty();
});

it('finds a name match across a different school', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    makeStudent(['first_name' => 'Jane', 'last_name' => 'Smith', 'school_id' => $schoolA->id]);

    $matches = app(DuplicateStudentService::class)->findMatches(
        candidate(['school_id' => $schoolB->id])
    );

    expect($matches)->toHaveCount(1);
});

it('excludes the student being edited from its own matches', function () {
    $profile = makeStudent(['first_name' => 'Jane', 'last_name' => 'Smith']);

    $matches = app(DuplicateStudentService::class)
        ->findMatches(candidate(), excludeUserId: $profile->user_id);

    expect($matches)->toBeEmpty();
});

it('excludes soft-deleted students', function () {
    $profile = makeStudent(['first_name' => 'Jane', 'last_name' => 'Smith']);
    $profile->delete();

    $matches = app(DuplicateStudentService::class)->findMatches(candidate());

    expect($matches)->toBeEmpty();
});

it('returns empty when the candidate has a blank name', function () {
    makeStudent(['first_name' => 'Jane', 'last_name' => 'Smith']);

    $matches = app(DuplicateStudentService::class)
        ->findMatches(candidate(['first_name' => '', 'last_name' => '']));

    expect($matches)->toBeEmpty();
});

it('exposes display context on the match', function () {
    $school = School::factory()->create(['display_name' => 'Maple Elementary']);
    makeStudent([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'school_id' => $school->id,
        'grade_level' => '5',
    ], ['email' => 'parent@example.com', 'username' => 'jane.smith.01']);

    $match = app(DuplicateStudentService::class)->findMatches(candidate())->first();

    expect($match->username)->toBe('jane.smith.01')
        ->and($match->schoolName)->toBe('Maple Elementary')
        ->and($match->email)->toBe('parent@example.com')
        ->and($match->gradeLevel)->toBe('5')
        ->and($match->showUrl)->toContain('/students/');
});
