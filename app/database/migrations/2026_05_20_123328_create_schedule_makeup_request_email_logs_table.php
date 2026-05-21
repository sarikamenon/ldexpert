<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_makeup_request_email_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('schedule_makeup_request_id')
                ->constrained('schedule_makeup_requests')
                ->cascadeOnDelete();

            $table->string('type', 30);

            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();

            $table->string('status', 16)->default('queued');
            $table->datetime('sent_at')->nullable();
            $table->datetime('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['schedule_makeup_request_id', 'type'],
                'smrel_request_type_idx'
            );
            $table->index(['status', 'created_at']);
            $table->index(['recipient_email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_makeup_request_email_logs');
    }
};
