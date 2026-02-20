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
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation to School or User (therapist)
            $table->morphs('ledgerable');

            // Transaction type
            $table->string('transaction_type', 50); // invoice_generated, payment_received, bill_generated, payment_made, expense

            // Financial details
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);

            // Reference to source document (polymorphic)
            $table->nullableMorphs('reference');

            // Additional context
            $table->text('notes')->nullable();

            // Audit trail
            $table->foreignId('recorded_by_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            // Indexes (morphs() and nullableMorphs() already create their own indexes)
            $table->index('transaction_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
