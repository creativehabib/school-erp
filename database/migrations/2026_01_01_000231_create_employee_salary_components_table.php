<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-employee salary structure, versioned by effective date.
 *
 * We never UPDATE a row to change someone's allowance: we close the old row
 * with `effective_to` and insert a new one. That way regenerating an old
 * payroll still produces the historically correct figure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 14, 2);
            $table->string('calculation', 30)->default('fixed');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from', 'effective_to'], 'emp_salary_effective_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
