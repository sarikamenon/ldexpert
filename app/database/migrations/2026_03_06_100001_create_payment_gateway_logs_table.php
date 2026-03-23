<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_gateway_transaction_id')
                ->constrained('payment_gateway_transactions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('action', 100);
            $table->string('direction', 20);
            $table->json('payload');
            $table->string('gateway_event_id')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('payment_gateway_transaction_id', 'pgl_transaction_id_index');
            $table->index('action');
            $table->index('gateway_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_logs');
    }
};
