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
        Schema::create('therapist_bill_payment_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('therapist_bill_id')
                ->constrained('therapist_bills')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('therapist_bill_payment_id');

            $table->decimal('allocated_amount', 10, 2);

            $table->timestamps();

            // Use explicit, short index names to avoid MySQL identifier length limits
            $table->index('therapist_bill_id', 'tbp_allocations_bill_idx');
            $table->index('therapist_bill_payment_id', 'tbp_allocations_payment_idx');

            // Explicit foreign key with short name to keep identifier length under MySQL limit
            $table->foreign('therapist_bill_payment_id', 'tbp_allocations_payment_fk')
                ->references('id')
                ->on('therapist_bill_payments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_bill_payment_allocations');
    }
};
