<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gap-free, race-free numbering for invoices, money receipts, expense
 * vouchers, payslips and admission numbers.
 *
 * Never generate these with MAX(number) + 1: two cashiers clicking "Collect"
 * in the same second will mint the same receipt number. DocumentNumberService
 * takes a row lock on this table instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->string('scope', 30)->default('global');
            $table->string('prefix', 20)->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->timestamps();

            $table->unique(['key', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
