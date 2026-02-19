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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // Category reference
            $table->foreignId('expense_category_id')
                ->constrained('expense_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Expense details
            $table->date('expense_date');
            $table->decimal('amount', 10, 2);
            $table->string('vendor_payee')->nullable();
            $table->text('description')->nullable();
            $table->string('reference')->nullable(); // Receipt number, etc.

            // Audit trail
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('expense_category_id');
            $table->index('expense_date');
            $table->index('created_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
