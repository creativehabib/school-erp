<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What you can charge for: Tuition Fee, Exam Fee, Admission Fee, Session
 * Charge, Transport, Library, Development Fee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 30)->unique();
            $table->string('category', 30)->default('tuition');
            $table->string('frequency', 20)->default('monthly');
            $table->boolean('is_refundable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_heads');
    }
};
