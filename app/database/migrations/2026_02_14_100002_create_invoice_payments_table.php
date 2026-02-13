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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();

            // Payment details
            $table->date('paid_at');
            $table->decimal('amount', 10, 2);
            $table->string('method', 50); // check, bank_transfer, ach, wire, cash, credit_card, other
            $table->string('reference')->nullable(); // Check number, transaction ID, etc.
            $table->text('notes')->nullable();

            // Audit trail
            $table->foreignId('recorded_by_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('paid_at');
            $table->index('method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
