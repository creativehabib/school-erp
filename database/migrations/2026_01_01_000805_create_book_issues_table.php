<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A loan.
 *
 * `borrower` is polymorphic over students and employees rather than pointing at
 * users, because a library card belongs to a student record - a student with no user
 * account (young children commonly have none) must still be able to borrow.
 *
 * The fine columns snapshot the rule that applied: fine_per_day and grace_days are
 * copied at issue time from library_rules. Recomputing the fine from today's policy
 * would change historical amounts. `transaction_id` links a collected fine to the
 * unified cash book, so library income appears in the financial dashboard instead of
 * sitting in a silo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_copy_id')->constrained()->restrictOnDelete();
            $table->string('borrower_type');
            $table->unsignedBigInteger('borrower_id');
            $table->foreignId('academic_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('issued')->index()->comment('BookIssueStatus enum');
            $table->date('issued_on');
            $table->date('due_date');
            $table->date('returned_on')->nullable();
            $table->unsignedSmallInteger('renewal_count')->default(0);
            $table->decimal('fine_per_day', 8, 2)->default(0)->comment('Snapshot of the rule at issue time');
            $table->unsignedSmallInteger('grace_days')->default(0)->comment('Snapshot of the rule at issue time');
            $table->decimal('max_fine', 10, 2)->nullable()->comment('Snapshot of the rule at issue time');
            $table->decimal('fine_amount', 10, 2)->default(0)->comment('Calculated on return');
            $table->decimal('fine_waived', 10, 2)->default(0);
            $table->decimal('fine_collected', 10, 2)->default(0);
            $table->string('waiver_reason')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Cash book entry for the collected fine');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();
            
            $table->index(['borrower_type', 'borrower_id', 'status'], 'book_issues_borrower_index');
            $table->index(['status', 'due_date'], 'book_issues_overdue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_issues');
    }
};
