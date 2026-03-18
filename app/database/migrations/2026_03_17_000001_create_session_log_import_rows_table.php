<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_log_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_log_import_id')->constrained('session_log_imports')->cascadeOnDelete();
            $table->integer('row_number');
            $table->string('reference_id', 100)->nullable()->index();
            $table->string('status', 32)->default('pending');
            $table->json('raw_data');
            $table->foreignId('session_log_id')->nullable()->constrained('session_logs')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('session_log_import_id');
            $table->index('status');
            $table->index(['session_log_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_log_import_rows');
    }
};
