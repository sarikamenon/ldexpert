<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_sub_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->cascadeOnDelete();
            $table->foreignId('requested_by_id')
                ->constrained('users');
            $table->text('reason')->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignId('accepted_by_id')
                ->nullable()
                ->constrained('users');
            $table->datetime('accepted_at')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'requested_by_id']);
            $table->index('schedule_id');
            $table->index(['accepted_by_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_sub_requests');
    }
};
