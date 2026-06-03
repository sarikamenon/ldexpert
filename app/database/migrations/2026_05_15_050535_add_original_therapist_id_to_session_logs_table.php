<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_logs', function (Blueprint $table) {
            $table->foreignId('original_therapist_id')
                ->nullable()
                ->after('therapist_id')
                ->constrained('users');

            $table->index(['original_therapist_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::table('session_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('original_therapist_id');
            $table->dropIndex(['original_therapist_id', 'session_date']);
        });
    }
};
