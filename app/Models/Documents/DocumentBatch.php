<?php

declare(strict_types=1);

namespace App\Models\Documents;

use App\Enums\BatchStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Exam\Exam;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bulk run: "admit cards for class 9 section A, Annual exam".
 *
 * Batches are rows rather than ephemeral queue state for two reasons. The operator
 * needs to come back tomorrow and re-download the same merged PDF without paying the
 * rendering cost again, and a partially failed run has to be resumable for only the
 * subset that failed - re-rendering 1,197 good cards to fix 3 is not acceptable when
 * each one spawns a Chromium page.
 */
class DocumentBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'document_template_id', 'academic_session_id', 'exam_id',
        'school_class_id', 'section_id', 'title', 'status',
        'total_count', 'generated_count', 'failed_count',
        'merged_path', 'filters', 'error', 'requested_by',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => BatchStatus::class,
            'filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    public function failedDocuments(): HasMany
    {
        return $this->documents()->where('status', DocumentStatus::Failed);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest('id');
    }

    public function progressPercent(): int
    {
        if ($this->total_count === 0) {
            return 0;
        }

        return (int) round((($this->generated_count + $this->failed_count) / $this->total_count) * 100);
    }

    public function isFinished(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Recount from the child rows and settle the final status.
     *
     * Counts are re-derived rather than incremented, because parallel queue workers
     * incrementing the same two columns will lose updates, and a batch that reports
     * 1,198 of 1,200 forever is a support ticket.
     */
    public function settle(): void
    {
        $generated = $this->documents()->where('status', DocumentStatus::Generated)->count();
        $failed = $this->documents()->where('status', DocumentStatus::Failed)->count();

        $status = match (true) {
            $failed === 0 && $generated > 0 => BatchStatus::Completed,
            $failed > 0 && $generated > 0 => BatchStatus::PartiallyFailed,
            $failed > 0 => BatchStatus::Failed,
            default => $this->status,
        };

        $this->forceFill([
            'generated_count' => $generated,
            'failed_count' => $failed,
            'status' => $status,
            'completed_at' => $status->isTerminal() ? now() : null,
        ])->save();
    }
}
