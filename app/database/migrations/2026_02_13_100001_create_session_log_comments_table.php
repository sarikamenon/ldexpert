<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('session_log_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_log_id')
                ->constrained('session_logs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('author_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->text('comment');
            $table->string('type', 32); // e.g. sent_back, therapist_reply
            $table->timestamps();
            $table->softDeletes();

            $table->index('session_log_id');
            $table->index('author_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_log_comments');
    }
};
