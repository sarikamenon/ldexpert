<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_makeup_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_calendar_event_id')
                ->nullable()
                ->constrained('school_calendar_events')
                ->nullOnDelete();
            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('users');
            $table->foreignId('therapist_id')
                ->constrained('users');

            $table->date('event_date');
            $table->date('reminder_date');
            $table->date('response_date');

            $table->string('status', 20)->default('pending');

            // Groups every row that belongs in one parent email. Rows in the same
            // batch share batch_number AND response_token. batch_number is the
            // canonical join key (sender + response endpoint group by it);
            // response_token is the URL key embedded in the email's buttons.
            $table->string('batch_number', 32);

            $table->datetime('reminder_sent_at')->nullable();

            $table->datetime('responded_at')->nullable();
            $table->string('responded_by_type', 16)->nullable();
            $table->foreignId('responded_by_user_id')
                ->nullable()
                ->constrained('users');
            $table->string('response_source', 32)->nullable();
            $table->string('reason')->nullable();

            $table->foreignId('makeup_schedule_id')
                ->nullable()
                ->constrained('schedules')
                ->nullOnDelete();

            $table->string('response_token', 64);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['school_calendar_event_id', 'schedule_id'],
                'smr_event_schedule_unique'
            );
            $table->index(['therapist_id', 'status']);
            $table->index(['reminder_date', 'status']);
            $table->index(['response_date', 'status']);
            $table->index('makeup_schedule_id');
            $table->index('response_token');
            $table->index('batch_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_makeup_requests');
    }
};
