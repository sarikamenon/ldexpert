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
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('display_name')->unique();
            $table->text('address')->nullable();
            $table->string('state', 2);
            $table->string('timezone', 64);
            $table->foreignId('manager_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('contact_first_name')->nullable();
            $table->string('contact_last_name')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('invoice_email')->nullable();

            $table->string('school_type', 32);
            $table->boolean('is_private_student')->default(false);
            $table->boolean('non_billable_scheduling')->default(false);
            $table->string('external_emr_name')->nullable();

            $table->string('status', 16)->default('active');
            $table->string('status_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('manager_id');
            $table->index('state');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
