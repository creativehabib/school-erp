<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bell schedule. Separated from the routine grid so that changing the second
 * period from 09:45 to 09:50 is one UPDATE, not one per section per weekday.
 *
 * `shift_id` is nullable so a school running one shift does not have to invent one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('name_bn', 60)->nullable();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_break')->default(false)->comment('Tiffin / assembly - no subject assigned');
            $table->unsignedSmallInteger('serial')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['shift_id', 'name'], 'periods_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
