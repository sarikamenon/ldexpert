<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->after('name')->default('');
        });

        // Backfill: set username = email for all existing users
        DB::statement('UPDATE users SET username = email');

        // Now make username unique (after backfill)
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->change();
        });

        // Drop unique constraint on email (keep the column, just remove uniqueness)
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }

    public function down(): void
    {
        // Re-add unique constraint on email
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->unique()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
