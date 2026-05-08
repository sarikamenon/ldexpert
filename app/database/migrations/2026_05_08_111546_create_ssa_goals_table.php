<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssa_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ssa_id')
                ->constrained('service_support_agreements')
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('users');
            $table->string('number', 50);
            $table->text('objective');
            $table->string('progress', 1000)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ssa_id', 'status']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssa_goals');
    }
};
