<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('creating a student assigns them to the therapist via pivot', function () {
    $therapist = User::factory()->therapist()->create();
    $this->actingAs($therapist);

    $response = $this->post('/therapist/students', [
        'name' => 'Student A',
        'email' => 'student.assign@example.com',
        'first_name' => 'Student',
        'last_name' => 'A',
        'password' => 'Secret123!',
    ]);

    $response->assertRedirect();

    $student = User::query()->where('email', 'student.assign@example.com')->firstOrFail();

    expect(DB::table('therapist_student')->where([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
    ])->exists())->toBeTrue();

    // deactivate then activate
    $this->patch("/therapist/students/{$student->id}/status/deactivate")->assertRedirect();
    expect(($student->fresh()->status instanceof \App\Enums\UserStatus) ? $student->fresh()->status->value : $student->fresh()->status)->toBe('inactive');

    $this->patch("/therapist/students/{$student->id}/status/activate")->assertRedirect();
    expect(($student->fresh()->status instanceof \App\Enums\UserStatus) ? $student->fresh()->status->value : $student->fresh()->status)->toBe('active');
});
