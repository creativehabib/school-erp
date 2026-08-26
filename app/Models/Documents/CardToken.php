<?php

declare(strict_types=1);

namespace App\Models\Documents;

use App\Models\Academic\AcademicSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A revocable public token behind a QR code.
 *
 * The QR on an ID card must not encode the student's name, phone number, or primary
 * key. Anyone can photograph a card, and anyone can iterate integers - encode the PK
 * and you have published a student directory to whoever finds a card on the road.
 *
 * Instead the QR encodes an opaque random token that resolves through this table to a
 * public verification page showing only what a gate guard needs: photo, name, class,
 * and whether the card is currently valid. A lost card is killed by setting
 * revoked_at, without changing the student's identity or invalidating anyone else's
 * card.
 */
class CardToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token', 'holder_type', 'holder_id', 'purpose',
        'academic_session_id', 'expires_at', 'revoked_at',
        'scan_count', 'last_scanned_at', 'last_scanned_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_scanned_at' => 'datetime',
        ];
    }

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Reuse the holder's live token or mint a new one.
     *
     * Reuse matters: minting a token per print run would mean reprinting a damaged
     * card silently invalidates nothing but fills the table with dead rows, and a
     * student holding two valid cards with different QR codes confuses the gate.
     */
    public static function issueFor(
        Model $holder,
        string $purpose = 'id_card',
        ?int $academicSessionId = null,
        ?int $validDays = null,
    ): self {
        $existing = static::query()
            ->where('holder_type', $holder->getMorphClass())
            ->where('holder_id', $holder->getKey())
            ->where('purpose', $purpose)
            ->active()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return static::create([
            'token' => static::generateToken(),
            'holder_type' => $holder->getMorphClass(),
            'holder_id' => $holder->getKey(),
            'purpose' => $purpose,
            'academic_session_id' => $academicSessionId,
            'expires_at' => $validDays !== null ? now()->addDays($validDays) : null,
        ]);
    }

    /**
     * 32 URL-safe random characters.
     *
     * Long enough that guessing is pointless and short enough that the QR stays
     * low-density, which matters when it is printed at 15mm on a laser printer and
     * scanned by a five-year-old phone.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::lower(Str::random(32));
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    public function recordScan(?string $ip = null): void
    {
        $this->increment('scan_count');
        $this->forceFill([
            'last_scanned_at' => now(),
            'last_scanned_ip' => $ip,
        ])->save();
    }

    /** The absolute URL encoded into the QR image. */
    public function verifyUrl(): string
    {
        return route('verify.card', ['token' => $this->token]);
    }
}
