<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Singleton row holding institution identity. This is deliberately a table
 * rather than a config file: EIIN, logo and the head teacher's signature are
 * printed on every certificate and must be editable by an Admin at runtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_profile', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->string('eiin', 20)->nullable()->index();
            $table->foreignId('board_id')->nullable()->constrained()->nullOnDelete();
            $table->string('board_school_code', 30)->nullable();

            $table->string('address_en')->nullable();
            $table->string('address_bn')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            $table->string('logo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('head_teacher_name')->nullable();
            $table->string('head_teacher_designation')->nullable();

            $table->year('established_year')->nullable();

            // Bangladesh government fiscal year runs July -> June.
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(7);
            $table->string('currency', 3)->default('BDT');
            $table->string('timezone', 40)->default('Asia/Dhaka');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_profile');
    }
};
