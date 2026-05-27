<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_makeup_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('availability_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['therapist_id', 'availability_date'], 'sma_therapist_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_makeup_availabilities');
    }
};
