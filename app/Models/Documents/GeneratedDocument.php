<?php

declare(strict_types=1);

namespace App\Models\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * One rendered artefact, addressable and re-downloadable.
 *
 * `subject` is polymorphic because the same table serves a student ID card, an
 * employee ID card, a payslip and a fee receipt.
 *
 * `payload` snapshots the data the document was rendered from. Same reasoning as
 * payslips: a testimonial issued in 2024 must reprint identically in 2027, even
 * though the student has left and the head teacher whose name is on it has retired.
 *
 * `print_count` is not vanity. Transfer certificates and testimonials are legal
 * documents that schools are expected to issue once; knowing a TC has been printed
 * four times is exactly the audit question that gets asked.
 */
class GeneratedDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_batch_id', 'document_template_id', 'type',
        'subject_type', 'subject_id', 'serial_no', 'status',
        'file_path', 'file_size', 'checksum', 'payload', 'error',
        'issued_by', 'issued_at', 'print_count', 'last_printed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'payload' => 'array',
            'issued_at' => 'datetime',
            'last_printed_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DocumentBatch::class, 'document_batch_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeOfType(Builder $query, DocumentType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeGenerated(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Generated);
    }

    public function exists(): bool
    {
        return filled($this->file_path) && Storage::disk('local')->exists($this->file_path);
    }

    public function contents(): ?string
    {
        return $this->exists() ? Storage::disk('local')->get($this->file_path) : null;
    }

    /**
     * Detect a file that has been swapped on disk.
     *
     * Worth checking before reprinting a transfer certificate: the stored checksum is
     * the only evidence that the PDF being handed over is the one the school issued.
     */
    public function isIntact(): bool
    {
        $contents = $this->contents();

        return $contents !== null
            && $this->checksum !== null
            && hash('sha256', $contents) === $this->checksum;
    }

    public function recordPrint(): void
    {
        $this->increment('print_count');
        $this->forceFill(['last_printed_at' => now()])->save();
    }

    public function downloadName(): string
    {
        return sprintf(
            '%s-%s.pdf',
            $this->type->slug(),
            $this->serial_no ?: $this->getKey(),
        );
    }
}
