<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qglob_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->date('requested_date');
            $table->time('requested_time');
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->text('admin_response')->nullable();
            $table->foreignId('responded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['requested_by_id', 'status']);
            $table->index(['requested_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qglob_requests');
    }
};
