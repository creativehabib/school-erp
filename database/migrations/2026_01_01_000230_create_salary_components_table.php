<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Definition of every earning and deduction line: House Rent, Medical
 * Allowance, Festival Bonus, Provident Fund, Income Tax, Absence Deduction.
 *
 * Keeping components as data (not hard-coded columns) means an Accountant can
 * add "Tiffin Allowance" next year without a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 30)->unique();
            $table->string('type', 10);                          // earning | deduction
            $table->string('calculation', 30)->default('fixed'); // fixed | percent_of_basic
            $table->decimal('default_value', 14, 2)->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('applies_to_all')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
