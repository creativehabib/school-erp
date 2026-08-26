<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where money physically sits: cash box, bank accounts, mobile wallets.
 * Created before HRM/Accounts because both payroll and fee collection point
 * at it. `current_balance` is a maintained cache; `transactions` is the truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('type', 20)->default('cash');
            $table->string('bank_name')->nullable();
            $table->string('branch')->nullable();
            $table->string('account_no', 40)->nullable();
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('current_balance', 16, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};
