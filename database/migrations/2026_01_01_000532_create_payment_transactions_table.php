<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gateway audit trail for bKash / Nagad / Rocket / SSLCommerz.
 *
 * Kept separate from `payments` because a gateway attempt may fail, be
 * abandoned mid-flow, or fire a duplicate callback. Only a COMPLETED
 * transaction creates a Payment row. `idempotency_key` is unique so a retried
 * webhook cannot double-credit a student's account, and the raw payloads are
 * retained for dispute resolution with the provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable'); // Invoice or Payment
            $table->string('gateway', 30);
            $table->string('gateway_txn_id', 100)->nullable();
            $table->string('gateway_payment_ref', 100)->nullable();

            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 20)->default('initiated')->index();

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('failure_reason')->nullable();

            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('gateway_txn_id');
            $table->index(['gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
