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
            // Drop old 'school' string column if it exists
            if (Schema::hasColumn('student_profiles', 'school')) {
                $table->dropColumn('school');
            }

            // Add school_id foreign key if it doesn't exist
            if (!Schema::hasColumn('student_profiles', 'school_id')) {
                $table->foreignId('school_id')
                    ->nullable()
                    ->after('id_number')
                    ->constrained('schools')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            // Drop foreign key and column
            if (Schema::hasColumn('student_profiles', 'school_id')) {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            }

            // Restore old 'school' string column
            if (!Schema::hasColumn('student_profiles', 'school')) {
                $table->string('school')->nullable()->after('id_number');
            }
        });
    }
};
