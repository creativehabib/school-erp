<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical location. Separate from category because where a book LIVES and what it
 * IS ABOUT are different questions, and the librarian searching for a misfiled copy
 * needs the first one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelves', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('code', 20)->nullable()->unique();
            $table->string('room', 60)->nullable();
            $table->string('rack', 30)->nullable();
            $table->string('row', 30)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelves');
    }
};
