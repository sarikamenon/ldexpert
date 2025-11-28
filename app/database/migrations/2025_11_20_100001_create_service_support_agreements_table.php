<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_support_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('primary_service_id')
                ->constrained('services')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('minutes_per_session');
            $table->string('frequency', 32); // weekly, bi_weekly, monthly, quarterly
            $table->unsignedTinyInteger('sessions_per_frequency');
            $table->unsignedInteger('tho_minutes'); // Total Hours Authorized
            $table->foreignId('assigned_therapist_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('status', 32)->default('pending'); // pending, active, completed, deactivated
            $table->unsignedInteger('served_minutes')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'status']);
            $table->index(['primary_service_id', 'status']);
            $table->index(['assigned_therapist_id', 'status']);
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_support_agreements');
    }
};
