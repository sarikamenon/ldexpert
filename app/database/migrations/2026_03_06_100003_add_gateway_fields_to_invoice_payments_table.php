<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->string('gateway', 50)->nullable()->after('notes');
            $table->string('gateway_transaction_id')->nullable()->after('gateway');

            $table->foreignId('payment_gateway_transaction_id')
                ->nullable()
                ->after('gateway_transaction_id')
                ->constrained('payment_gateway_transactions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropForeign(['payment_gateway_transaction_id']);
            $table->dropColumn(['gateway', 'gateway_transaction_id', 'payment_gateway_transaction_id']);
        });
    }
};
