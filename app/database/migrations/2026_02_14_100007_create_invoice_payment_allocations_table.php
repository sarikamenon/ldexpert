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
        Schema::create('invoice_payment_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('invoice_payment_id')
                ->constrained('invoice_payments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->decimal('allocated_amount', 10, 2);

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('invoice_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payment_allocations');
    }
};
