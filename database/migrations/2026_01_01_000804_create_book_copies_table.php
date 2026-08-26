<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One physical object, one row, one accession number.
 *
 * `accession_no` is the number the librarian writes inside the front cover and the
 * value encoded in the copy's barcode, so it is unique and human-readable rather
 * than the primary key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shelf_id')->nullable()->constrained()->nullOnDelete();
            $table->string('accession_no', 40)->unique();
            $table->string('barcode', 60)->nullable()->unique();
            $table->string('status', 20)->default('available')->index()->comment('BookCopyStatus enum');
            $table->string('condition', 20)->default('good');
            $table->date('acquired_on')->nullable();
            $table->string('acquisition_source')->nullable()->comment('Purchase, government supply, donation');
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            
            $table->index(['book_id', 'status'], 'book_copies_availability_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copies');
    }
};
