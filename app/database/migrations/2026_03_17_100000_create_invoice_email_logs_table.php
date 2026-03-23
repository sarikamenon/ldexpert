<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('recipient_email', 255);
            $table->text('custom_message')->nullable();
            $table->foreignId('sent_by_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_email_logs');
    }
};
