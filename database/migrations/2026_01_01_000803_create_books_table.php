<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A title. Physical copies live in book_copies.
 *
 * Splitting title from copy is the decision that makes the whole module work. A
 * single `quantity` integer on this table cannot answer "which copy is overdue",
 * cannot record that copy 3 is water-damaged, and races under concurrent issue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shelf_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('title_bn')->nullable();
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->string('edition', 40)->nullable();
            $table->string('isbn', 20)->nullable()->index();
            $table->string('language', 30)->default('Bengali');
            $table->year('published_year')->nullable();
            $table->unsignedSmallInteger('pages')->nullable();
            $table->decimal('price', 10, 2)->nullable()->comment('Replacement value for lost copies');
            $table->string('cover_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_reference_only')->default(false)->comment('Read in library, never issued');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            
            $table->index(['title'], 'books_title_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
