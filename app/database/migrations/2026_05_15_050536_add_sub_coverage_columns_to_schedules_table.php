<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('sub_therapist_id')
                ->nullable()
                ->after('status')
                ->constrained('users');
            $table->string('sub_request_status', 16)
                ->nullable()
                ->after('sub_therapist_id');

            $table->index(['sub_therapist_id', 'schedule_date']);
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['sub_therapist_id', 'schedule_date']);
            $table->dropConstrainedForeignId('sub_therapist_id');
            $table->dropColumn('sub_request_status');
        });
    }
};
