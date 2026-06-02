<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_calendar_events', function (Blueprint $table) {
            $table->boolean('request_makeup')->default(false)->after('end_date');
            $table->date('reminder_date')->nullable()->after('request_makeup');
            $table->date('response_date')->nullable()->after('reminder_date');
        });
    }

    public function down(): void
    {
        Schema::table('school_calendar_events', function (Blueprint $table) {
            $table->dropColumn(['request_makeup', 'reminder_date', 'response_date']);
        });
    }
};
