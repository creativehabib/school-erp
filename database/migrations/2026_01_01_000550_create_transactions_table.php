<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNIFIED CASH BOOK — the single table the financial dashboard reads.
 *
 * Every Payment (in), Expense (out) and paid Payslip (out) writes one row here
 * via an observer/action. Without this you end up UNION-ing three tables with
 * different shapes on every chart, which gets slow and inconsistent fast.
 *
 * One month of income vs expense becomes:
 *   SELECT direction, SUM(amount) FROM transactions
 *   WHERE transaction_date BETWEEN ? AND ? GROUP BY direction
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->string('direction', 10);   // in | out
            $table->decimal('amount', 16, 2);
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();

            $table->nullableMorphs('source');  // Payment | Expense | Payslip
            $table->string('category', 50)->nullable();
            $table->string('description')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('transaction_date');
            $table->index(['transaction_date', 'direction'], 'transactions_date_direction_index');
            $table->index(['direction', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
