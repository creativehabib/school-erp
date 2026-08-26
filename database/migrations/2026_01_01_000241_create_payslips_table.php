<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One payslip per employee per payroll run.
 *
 * Name / designation / department are DENORMALISED SNAPSHOTS on purpose. A
 * payslip is a legal document: if a teacher is promoted in November, the
 * August payslip PDF must still show the August designation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('slip_no', 40)->unique();

            $table->string('employee_name');
            $table->string('employee_code', 30)->nullable();
            $table->string('designation_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('bank_account_no', 40)->nullable();

            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->decimal('gross_earnings', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('net_payable', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);

            $table->decimal('payable_days', 5, 1)->default(0);
            $table->decimal('present_days', 5, 1)->default(0);
            $table->decimal('absent_days', 5, 1)->default(0);
            $table->decimal('leave_days', 5, 1)->default(0);

            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->string('payment_method', 20)->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['payroll_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
