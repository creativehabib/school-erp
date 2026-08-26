<?php

declare(strict_types=1);

namespace App\Models\Documents;

use App\Enums\DocumentType;
use App\Models\User;
use App\Support\PageSetup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customisable template per document type.
 *
 * SECURITY. `body` holds markup an administrator typed. It must never be handed to
 * the full Blade compiler: Blade compiles to PHP, so anyone who can save a template
 * could save executable code and escalate from "can design an ID card" to "can read
 * the database". TemplateRenderer resolves a whitelisted placeholder set instead,
 * and `is_system` templates - the ones that ship with the app and live under
 * resources/views/pdf/ - are the only ones rendered as real Blade views.
 */
class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'paper_size', 'orientation',
        'margin_top', 'margin_right', 'margin_bottom', 'margin_left',
        'per_page', 'card_width_mm', 'card_height_mm',
        'show_qr', 'show_barcode', 'show_photo', 'show_signature',
        'background_path', 'body', 'styles',
        'is_system', 'is_default', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'show_qr' => 'boolean',
            'show_barcode' => 'boolean',
            'show_photo' => 'boolean',
            'show_signature' => 'boolean',
            'is_system' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, DocumentType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * The template to use when the operator did not pick one.
     *
     * Falls back to any active template of the type rather than returning null, so a
     * school that deleted the is_default flag can still print.
     */
    public static function defaultFor(DocumentType $type): ?self
    {
        return static::query()
            ->ofType($type)
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * Geometry for the renderer.
     *
     * Card dimensions are only applied when the type is actually a card AND per_page is
     * 1. A template that lays 10 ID cards out on a sheet must keep the sheet's paper
     * size; handing the renderer 54mm would print one card and drop the other nine.
     */
    public function pageSetup(): PageSetup
    {
        $singleCard = $this->type->isCard()
            && (int) $this->per_page === 1
            && $this->card_width_mm !== null
            && $this->card_height_mm !== null;

        if ($singleCard) {
            return PageSetup::card(
                widthMm: (int) $this->card_width_mm,
                heightMm: (int) $this->card_height_mm,
                title: $this->name,
            );
        }

        return new PageSetup(
            paperSize: $this->paper_size,
            orientation: $this->orientation,
            marginTop: $this->margin_top,
            marginRight: $this->margin_right,
            marginBottom: $this->margin_bottom,
            marginLeft: $this->margin_left,
            title: $this->name,
        );
    }

    /** True when this template's markup is user-authored and must be sandboxed. */
    public function isUserAuthored(): bool
    {
        return ! $this->is_system && filled($this->body);
    }

    public function viewName(): string
    {
        return $this->type->view();
    }
}
