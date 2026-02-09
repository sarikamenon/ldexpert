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
        Schema::create('ssa_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ssa_import_id')
                ->constrained('ssa_imports')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->integer('row_number'); // 1-indexed, excluding header
            $table->string('status', 32)->default('pending'); // pending, processing, done, duplicate, validation_error
            $table->json('raw_data'); // Original CSV row data
            $table->foreignId('ssa_id')
                ->nullable()
                ->constrained('service_support_agreements')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('ssa_import_id');
            $table->index('status');
            $table->index('row_number');
            $table->index(['ssa_import_id', 'row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ssa_import_rows');
    }
};
