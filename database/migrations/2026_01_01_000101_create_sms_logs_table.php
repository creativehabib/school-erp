<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every outbound SMS, with the gateway response. Two reasons this is a table and
 * not a log file: schools are billed per SMS and will dispute counts, and when a
 * guardian says "I never got the OTP" you need to answer from data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('purpose', 40)->index();
            $table->text('message');
            $table->unsignedSmallInteger('parts')->default(1);
            $table->boolean('is_unicode')->default(false);
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_message_id')->nullable();
            $table->string('status', 20)->default('queued')->index();
            $table->text('error')->nullable();
            $table->decimal('cost', 8, 4)->nullable();
            $table->nullableMorphs('recipient');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
