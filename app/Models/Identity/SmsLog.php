<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\SmsStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit trail for every outbound SMS.
 *
 * Two operational reasons this is a table: schools are billed per SMS part and will
 * dispute the count, and when a guardian says the OTP never arrived you need to
 * answer from data rather than from sympathy.
 */
class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone', 'purpose', 'message', 'parts', 'is_unicode', 'gateway',
        'gateway_message_id', 'status', 'error', 'cost',
        'recipient_type', 'recipient_id', 'sent_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_unicode' => 'boolean',
            'status' => SmsStatus::class,
            'cost' => 'decimal:4',
            'sent_at' => 'datetime',
        ];
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', SmsStatus::Failed);
    }

    /**
     * Bengali text costs three times as much to send.
     *
     * A GSM-7 part carries 160 characters; a UCS-2 part carries 70. Any Bengali
     * character forces the whole message to UCS-2, so a 150-character Bengali
     * notice is three billable parts, not one. Schools budget by the part, so this
     * has to be computed before sending, not discovered on the invoice.
     */
    public static function estimateParts(string $message): array
    {
        $isUnicode = (bool) preg_match('/[^\x00-\x7F]/', $message);
        $length = mb_strlen($message);
        $perPart = $isUnicode ? 70 : 160;

        return [
            'is_unicode' => $isUnicode,
            'length' => $length,
            'parts' => max(1, (int) ceil($length / $perPart)),
        ];
    }
}
