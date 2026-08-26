<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subject catalogue. Deliberately free of class-specific data: the same "Mathematics"
 * row is offered to class 6 and class 9 with different full marks and different
 * component splits, and that variation belongs on class_subjects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 20)->nullable()->unique();
            $table->string('board_subject_code', 20)->nullable()->comment('Board paper code printed on admit cards');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
