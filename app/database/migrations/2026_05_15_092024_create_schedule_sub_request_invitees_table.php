<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_sub_request_invitees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_sub_request_id')
                ->constrained('schedule_sub_requests')
                ->cascadeOnDelete();
            $table->foreignId('therapist_id')
                ->constrained('users');
            $table->string('status', 16)->default('invited');
            $table->datetime('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['schedule_sub_request_id', 'therapist_id'], 'sub_request_invitee_unique');
            $table->index(['therapist_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_sub_request_invitees');
    }
};
