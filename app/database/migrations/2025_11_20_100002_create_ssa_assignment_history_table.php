<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssa_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ssa_id')
                ->constrained('service_support_agreements')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('therapist_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('action', 32); // assigned, unassigned
            $table->foreignId('assigned_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->index('ssa_id');
            $table->index('therapist_id');
            $table->index('assigned_at');
            $table->index(['ssa_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssa_assignment_history');
    }
};
