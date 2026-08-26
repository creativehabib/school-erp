<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table is `school_classes` (not `classes`) because `Class` is a reserved word
 * in PHP and cannot be used as a model name. Model: SchoolClass.
 *
 * `level` is the numeric ordering (6 for Class Six) and drives promotion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 20)->nullable()->unique();
            $table->unsignedSmallInteger('level')->index();
            $table->boolean('has_groups')->default(false); // Class 9+ splits into Science/Business/Humanities
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
