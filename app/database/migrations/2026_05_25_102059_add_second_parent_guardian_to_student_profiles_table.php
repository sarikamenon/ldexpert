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
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('parent_guardian_2_name')->nullable()->after('schedule_email');
            $table->string('parent_guardian_2_email')->nullable()->after('parent_guardian_2_name');
            $table->string('parent_guardian_2_phone')->nullable()->after('parent_guardian_2_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'parent_guardian_2_name',
                'parent_guardian_2_email',
                'parent_guardian_2_phone',
            ]);
        });
    }
};
