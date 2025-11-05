<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('therapist_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('therapist_profiles', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('student_profiles', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('parent_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('parent_profiles', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('admin_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_profiles', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('therapist_student', function (Blueprint $table) {
            if (! Schema::hasColumn('therapist_student', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('therapist_student', function (Blueprint $table) {
            if (Schema::hasColumn('therapist_student', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('admin_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('admin_profiles', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('parent_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('parent_profiles', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('student_profiles', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('therapist_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('therapist_profiles', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
