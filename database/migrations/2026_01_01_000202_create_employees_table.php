<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teachers AND non-teaching staff. `user_id` is nullable because HR often
 * onboards a person before issuing login credentials.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code', 30)->unique();

            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();

            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 20)->default('teaching');
            $table->string('employment_type', 20)->default('permanent');

            // MPO index number: required for government salary subvention in BD.
            $table->string('mpo_index_no', 30)->nullable();

            $table->date('joining_date');
            $table->date('confirmation_date')->nullable();
            $table->date('resignation_date')->nullable();
            $table->string('status', 20)->default('active')->index();

            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('nid', 30)->nullable();
            $table->string('tin', 30)->nullable();

            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();

            $table->string('highest_qualification')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();

            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->string('salary_payment_mode', 20)->default('bank');
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_account_no', 40)->nullable();
            $table->string('mobile_wallet_no', 20)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
