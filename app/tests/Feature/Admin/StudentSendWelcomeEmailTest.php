<?php

declare(strict_types=1);

use App\Mail\WelcomeStudentMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->admin()->create();
});

it('sends login details to selected students', function () {
    $students = User::factory()->student()->count(2)->create();

    $response = $this->actingAs($this->admin)->postJson(
        route('admin.students.send-welcome-email'),
        ['student_ids' => $students->pluck('id')->all()],
    );

    $response->assertOk()
        ->assertJson(['sent' => 2, 'failed' => 0]);

    Mail::assertSent(WelcomeStudentMail::class, 2);
});

it('sends login details to a single student', function () {
    $student = User::factory()->student()->create();

    $response = $this->actingAs($this->admin)->postJson(
        route('admin.students.send-welcome-email'),
        ['student_ids' => [$student->id]],
    );

    $response->assertOk()->assertJson(['sent' => 1, 'failed' => 0]);
    Mail::assertSent(WelcomeStudentMail::class, 1);
});

it('rejects an empty student_ids array', function () {
    $response = $this->actingAs($this->admin)->postJson(
        route('admin.students.send-welcome-email'),
        ['student_ids' => []],
    );

    $response->assertStatus(422)->assertJsonValidationErrors('student_ids');
    Mail::assertNothingSent();
});

it('rejects a missing student_ids key', function () {
    $response = $this->actingAs($this->admin)->postJson(
        route('admin.students.send-welcome-email'),
        [],
    );

    $response->assertStatus(422)->assertJsonValidationErrors('student_ids');
    Mail::assertNothingSent();
});

it('rejects a non-existent student id', function () {
    $response = $this->actingAs($this->admin)->postJson(
        route('admin.students.send-welcome-email'),
        ['student_ids' => [999999]],
    );

    $response->assertStatus(422)->assertJsonValidationErrors('student_ids.0');
    Mail::assertNothingSent();
});

it('rejects a non-student user id', function () {
    $therapist = User::factory()->therapist()->create();

    $response = $this->actingAs($this->admin)->postJson(
        route('admin.students.send-welcome-email'),
        ['student_ids' => [$therapist->id]],
    );

    $response->assertStatus(422)->assertJsonValidationErrors('student_ids.0');
    Mail::assertNothingSent();
});

it('forbids non-admin users', function () {
    $student = User::factory()->student()->create();

    $response = $this->actingAs(User::factory()->student()->create())->postJson(
        route('admin.students.send-welcome-email'),
        ['student_ids' => [$student->id]],
    );

    $response->assertForbidden();
    Mail::assertNothingSent();
});

it('redirects unauthenticated users', function () {
    $student = User::factory()->student()->create();

    $response = $this->postJson(
        route('admin.students.send-welcome-email'),
        ['student_ids' => [$student->id]],
    );

    $response->assertUnauthorized();
    Mail::assertNothingSent();
});
