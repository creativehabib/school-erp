<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrowing policy per borrower type, effective-dated.
 *
 * Effective dating for the same reason salary structures are versioned: raising the
 * fine from 2 to 5 taka per day in June must not retroactively inflate a fine that
 * was settled in March. A returned loan reads the rule that was in force on its own
 * issue date, never today's rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_rules', function (Blueprint $table) {
            $table->id();
            $table->string('borrower_type', 20)->comment('BorrowerType enum');
            $table->unsignedSmallInteger('max_books')->default(2);
            $table->unsignedSmallInteger('loan_days')->default(14);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->decimal('fine_per_day', 8, 2)->default(0);
            $table->decimal('max_fine', 10, 2)->nullable()->comment('Cap, so a forgotten book does not accrue forever');
            $table->decimal('lost_book_multiplier', 5, 2)->default(1.00)->comment('x price charged when a copy is written off');
            $table->unsignedSmallInteger('max_renewals')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['borrower_type', 'effective_from'], 'library_rules_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_rules');
    }
};
