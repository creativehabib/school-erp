<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = [
        'phone', 'purpose', 'code_hash', 'attempts',
        'expires_at', 'consumed_at', 'request_ip',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    protected $hidden = ['code_hash'];

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', 5);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
