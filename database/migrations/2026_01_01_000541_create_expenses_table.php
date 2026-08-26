<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 40)->unique();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();

            $table->string('title');
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');

            $table->string('method', 20)->default('cash');
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('paid_to')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('attachment_path')->nullable();

            $table->string('status', 20)->default('approved')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('expense_date');
            $table->index(['expense_category_id', 'expense_date'], 'expense_category_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
