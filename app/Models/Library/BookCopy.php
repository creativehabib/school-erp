<?php

declare(strict_types=1);

namespace App\Models\Library;

use App\Enums\BookCopyStatus;
use App\Enums\BookIssueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One physical object, one accession number.
 *
 * `accession_no` is the number written inside the front cover and encoded in the
 * copy's barcode, so it is unique and human-readable rather than the primary key.
 */
class BookCopy extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id', 'shelf_id', 'accession_no', 'barcode', 'status',
        'condition', 'acquired_on', 'acquisition_source', 'purchase_price', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookCopyStatus::class,
            'acquired_on' => 'date',
            'purchase_price' => 'decimal:2',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(BookIssue::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', BookCopyStatus::Available);
    }

    /** The open loan, if this copy is out. */
    public function currentIssue(): ?BookIssue
    {
        return $this->issues()
            ->where('status', BookIssueStatus::Issued)
            ->latest('issued_on')
            ->first();
    }

    public function isLendable(): bool
    {
        return $this->status->isLendable();
    }

    public function label(): string
    {
        return sprintf('%s (%s)', $this->book?->title ?? 'Unknown', $this->accession_no);
    }

    /** Value encoded in the copy's printed barcode. */
    public function barcodeValue(): string
    {
        return $this->barcode ?: $this->accession_no;
    }
}
