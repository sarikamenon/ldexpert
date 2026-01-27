<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('title');
            $table->string('event_type', 32);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('school_id');
            $table->index('event_type');
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['school_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_calendar_events');
    }
};
