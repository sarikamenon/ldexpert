<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('remindable_type');
            $table->unsignedBigInteger('remindable_id');
            $table->string('reminder_type', 30);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['remindable_type', 'remindable_id']);
            $table->index('reminder_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_reminders');
    }
};
