<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_sub_ssas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_sub_request_id')
                ->constrained('schedule_sub_requests')
                ->cascadeOnDelete();
            $table->foreignId('schedule_id')
                ->constrained('schedules');
            $table->foreignId('ssa_id')
                ->constrained('service_support_agreements');
            $table->foreignId('sub_therapist_id')
                ->constrained('users');
            $table->foreignId('student_id')
                ->constrained('users');
            $table->foreignId('service_id')
                ->constrained('services');
            $table->foreignId('school_id')
                ->nullable()
                ->constrained('schools');
            $table->date('session_date');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_therapist_id', 'session_date', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_sub_ssas');
    }
};
