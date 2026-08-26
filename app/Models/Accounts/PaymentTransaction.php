<?php

declare(strict_types=1);

namespace App\Models\Accounts;

use App\Enums\GatewayStatus;
use App\Enums\PaymentGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * bKash / Nagad / Rocket / SSLCommerz attempt log.
 *
 * Only a Completed transaction should produce a Payment. Keep the raw
 * payloads: when a guardian insists "the money left my bKash", this table is
 * the only thing that settles the argument.
 */
class PaymentTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'status' => GatewayStatus::class,
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', GatewayStatus::Completed);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [GatewayStatus::Initiated, GatewayStatus::Pending]);
    }

    public function isCompleted(): bool
    {
        return $this->status === GatewayStatus::Completed;
    }
}
