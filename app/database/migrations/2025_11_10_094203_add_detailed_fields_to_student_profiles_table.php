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
            // Name fields
            $table->string('first_name')->nullable()->after('user_id');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('full_name')->nullable()->after('last_name');

            // School and ID
            $table->string('school')->nullable()->after('full_name');
            $table->string('id_number')->nullable()->after('school');

            // Timezone and Gender
            $table->string('timezone')->nullable()->after('id_number');
            $table->string('gender')->nullable()->after('timezone');

            // Address fields
            $table->text('address')->nullable()->after('gender');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('zip_code', 20)->nullable()->after('state');

            // Parent/Guardian information
            $table->string('parent_guardian_name')->nullable()->after('zip_code');
            $table->string('parent_guardian_email')->nullable()->after('parent_guardian_name');
            $table->string('parent_guardian_phone')->nullable()->after('parent_guardian_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'middle_name',
                'last_name',
                'full_name',
                'school',
                'id_number',
                'timezone',
                'gender',
                'address',
                'city',
                'state',
                'zip_code',
                'parent_guardian_name',
                'parent_guardian_email',
                'parent_guardian_phone',
            ]);
        });
    }
};
