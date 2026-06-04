<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Set once, when the first-schedule reminder email is queued for a
            // private-student school, so the email fires only once per family (§9).
            $table->timestamp('first_schedule_notified_at')->nullable()->after('is_private_student');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('first_schedule_notified_at');
        });
    }
};
