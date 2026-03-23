<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('position_id')->nullable()->after('comments');
        });

        // Migrate existing data: match position string to positions.name
        DB::statement('
            UPDATE therapist_profiles
            INNER JOIN positions ON positions.name = therapist_profiles.position
            SET therapist_profiles.position_id = positions.id
        ');

        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->foreign('position_id')
                ->references('id')
                ->on('positions')
                ->nullOnDelete();

            $table->dropIndex(['position']);
            $table->dropColumn('position');
        });
    }

    public function down(): void
    {
        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->string('position', 32)->nullable()->after('comments');
        });

        // Populate position from FK join
        DB::statement('
            UPDATE therapist_profiles
            INNER JOIN positions ON positions.id = therapist_profiles.position_id
            SET therapist_profiles.position = positions.name
        ');

        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropColumn('position_id');
        });
    }
};
