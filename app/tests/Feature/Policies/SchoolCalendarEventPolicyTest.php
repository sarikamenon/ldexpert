<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\Role;
use App\Models\SchoolCalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SchoolCalendarEventPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_any_and_view(): void
    {
        $admin = User::factory()->admin()->create();
        $event = SchoolCalendarEvent::factory()->create();

        $this->assertTrue($admin->can('viewAny', SchoolCalendarEvent::class));
        $this->assertTrue($admin->can('view', $event));
    }

    public function test_therapist_can_view_any_and_view(): void
    {
        $therapist = User::factory()->therapist()->create();
        $event = SchoolCalendarEvent::factory()->create();

        $this->assertTrue($therapist->can('viewAny', SchoolCalendarEvent::class));
        $this->assertTrue($therapist->can('view', $event));
    }

    public function test_non_admin_non_therapist_cannot_view(): void
    {
        $student = User::factory()->create(['role' => Role::STUDENT]);
        $parent = User::factory()->create(['role' => Role::PARENT]);
        $event = SchoolCalendarEvent::factory()->create();

        $this->assertFalse($student->can('viewAny', SchoolCalendarEvent::class));
        $this->assertFalse($student->can('view', $event));
        $this->assertFalse($parent->can('viewAny', SchoolCalendarEvent::class));
        $this->assertFalse($parent->can('view', $event));
    }

    public function test_only_admin_can_create_update_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();
        $event = SchoolCalendarEvent::factory()->create();

        $this->assertTrue($admin->can('create', SchoolCalendarEvent::class));
        $this->assertTrue($admin->can('update', $event));
        $this->assertTrue($admin->can('delete', $event));

        $this->assertFalse($therapist->can('create', SchoolCalendarEvent::class));
        $this->assertFalse($therapist->can('update', $event));
        $this->assertFalse($therapist->can('delete', $event));
    }
}
