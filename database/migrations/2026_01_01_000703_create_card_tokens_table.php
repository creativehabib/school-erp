<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revocable public tokens for QR verification.
 *
 * The QR code on an ID card must not encode the student's name, phone number, or the
 * primary key. Anyone can photograph a card and anyone can iterate integers. Instead
 * the QR encodes an opaque random token that resolves through this table to a public
 * verification page, and a lost card is invalidated by setting revoked_at - without
 * reissuing the student's identity or breaking older valid cards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->morphs('holder');
            $table->string('purpose', 30)->default('id_card');
            $table->foreignId('academic_session_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('scan_count')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->string('last_scanned_ip', 45)->nullable();
            $table->timestamps();
            
            $table->index(['holder_type', 'holder_id', 'purpose', 'revoked_at'], 'card_tokens_holder_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_tokens');
    }
};
