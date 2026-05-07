<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LeadNoteLengthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_timeline_note_exceeding_two_thousand_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = Lead::factory()->create(['created_by' => $admin->id]);

        $body = str_repeat('n', 5000);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.leads.notes.store', $lead), [
                'note' => $body,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('lead_notes', [
            'lead_id' => $lead->id,
            'author_id' => $admin->id,
            'note' => $body,
        ]);
    }

    public function test_admin_cannot_add_empty_timeline_note(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = Lead::factory()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.leads.notes.store', $lead), [
                'note' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);
    }

    public function test_admin_can_create_lead_with_long_follow_up_notes(): void
    {
        $admin = User::factory()->admin()->create();
        $notes = str_repeat('f', 2500);

        $response = $this->actingAs($admin)
            ->post(route('admin.leads.store'), [
                'first_name' => 'Test',
                'last_name' => 'Lead',
                'follow_up_notes' => $notes,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'first_name' => 'Test',
            'last_name' => 'Lead',
            'follow_up_notes' => $notes,
        ]);
    }
}
