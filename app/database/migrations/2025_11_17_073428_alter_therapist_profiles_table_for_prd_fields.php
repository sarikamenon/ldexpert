<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete existing therapist profiles as they don't match new schema
        DB::table('therapist_profiles')->delete();

        Schema::table('therapist_profiles', function (Blueprint $table) {
            // Drop existing columns if they exist
            if (Schema::hasColumn('therapist_profiles', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('therapist_profiles', 'license_number')) {
                $table->dropColumn('license_number');
            }
            if (Schema::hasColumn('therapist_profiles', 'specialization')) {
                $table->dropColumn('specialization');
            }
            if (Schema::hasColumn('therapist_profiles', 'years_of_experience')) {
                $table->dropColumn('years_of_experience');
            }
            if (Schema::hasColumn('therapist_profiles', 'bio')) {
                $table->dropColumn('bio');
            }
        });

        Schema::table('therapist_profiles', function (Blueprint $table) {
            // Add PRD-specified columns only if they don't exist
            if (! Schema::hasColumn('therapist_profiles', 'employee_type')) {
                $table->string('employee_type', 10)->after('user_id');
            }
            if (! Schema::hasColumn('therapist_profiles', 'title')) {
                $table->string('title', 10)->after('employee_type');
            }
            if (! Schema::hasColumn('therapist_profiles', 'first_name')) {
                $table->string('first_name')->after('title');
            }
            if (! Schema::hasColumn('therapist_profiles', 'last_name')) {
                $table->string('last_name')->after('first_name');
            }
            if (! Schema::hasColumn('therapist_profiles', 'personal_email')) {
                $table->string('personal_email')->after('last_name');
            }
            if (! Schema::hasColumn('therapist_profiles', 'phone')) {
                $table->string('phone', 32)->after('personal_email');
            }
            if (! Schema::hasColumn('therapist_profiles', 'ld_email')) {
                $table->string('ld_email')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('therapist_profiles', 'address')) {
                $table->text('address')->nullable()->after('ld_email');
            }
            if (! Schema::hasColumn('therapist_profiles', 'comments')) {
                $table->text('comments')->nullable()->after('address');
            }
            if (! Schema::hasColumn('therapist_profiles', 'position')) {
                $table->string('position', 32)->after('comments');
            }
            if (! Schema::hasColumn('therapist_profiles', 'state')) {
                $table->string('state', 2)->after('position');
            }
            if (! Schema::hasColumn('therapist_profiles', 'timezone')) {
                $table->string('timezone', 64)->after('state');
            }
            if (! Schema::hasColumn('therapist_profiles', 'manager_id')) {
                $table->foreignId('manager_id')
                    ->after('timezone')
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }
            if (! Schema::hasColumn('therapist_profiles', 'dob')) {
                $table->date('dob')->nullable()->after('manager_id');
            }

            // Add soft deletes if not present
            if (! Schema::hasColumn('therapist_profiles', 'deleted_at')) {
                $table->softDeletes();
            }

            // Add indexes if they don't exist
            if (! $this->hasIndex('therapist_profiles', 'therapist_profiles_manager_id_index')) {
                $table->index('manager_id');
            }
            if (! $this->hasIndex('therapist_profiles', 'therapist_profiles_state_index')) {
                $table->index('state');
            }
            if (! $this->hasIndex('therapist_profiles', 'therapist_profiles_position_index')) {
                $table->index('position');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        foreach ($indexes as $idx) {
            if ($idx->Key_name === $index) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapist_profiles', function (Blueprint $table) {
            // Remove indexes
            $table->dropIndex(['manager_id']);
            $table->dropIndex(['state']);
            $table->dropIndex(['position']);

            // Drop PRD columns
            $table->dropSoftDeletes();
            $table->dropColumn([
                'employee_type',
                'title',
                'first_name',
                'last_name',
                'personal_email',
                'phone',
                'ld_email',
                'address',
                'comments',
                'position',
                'state',
                'timezone',
                'manager_id',
                'dob',
            ]);

            // Restore original columns
            $table->string('phone')->nullable();
            $table->string('license_number')->nullable();
            $table->string('specialization')->nullable();
            $table->integer('years_of_experience')->nullable();
            $table->text('bio')->nullable();
        });
    }
};
