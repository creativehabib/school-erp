<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS OTP for phone-first login. Codes are stored hashed so a database leak
 * does not hand over live credentials, and `attempts` lets you lock out
 * brute-force guessing of a 6-digit code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('purpose', 30)->default('login');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'consumed_at'], 'otp_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
