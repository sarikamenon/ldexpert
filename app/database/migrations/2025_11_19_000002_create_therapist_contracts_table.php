<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapist_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')
                ->constrained('therapist_profiles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('therapist_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_contracts');
    }
};
