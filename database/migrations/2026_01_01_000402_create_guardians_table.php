<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "Father" role in the brief, modelled as a general Guardian so mothers
 * and legal guardians work with the same code path. A guardian is a separate
 * entity from the student so one father with three children in the school has
 * ONE login and sees all three.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->string('relation', 20)->default('father');
            $table->string('phone', 20)->index();
            $table->string('alt_phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('nid', 30)->nullable();
            $table->string('occupation')->nullable();
            $table->string('workplace')->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
