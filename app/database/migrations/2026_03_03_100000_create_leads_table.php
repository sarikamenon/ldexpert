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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Student info (mirrors student profile, most nullable at lead stage)
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('grade_level')->nullable();

            // Parent/Guardian info
            $table->string('parent_guardian_name')->nullable();
            $table->string('parent_guardian_email')->nullable();
            $table->string('parent_guardian_phone')->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code', 20)->nullable();

            // Pipeline
            $table->string('status', 20)->default('inquiry');
            $table->text('status_reason')->nullable();
            $table->string('source')->nullable();

            // Follow-up
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_notes')->nullable();

            // Tracking
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('converted_student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('follow_up_date');
            $table->index('source');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
