<?php

declare(strict_types=1);

namespace App\Models\Accounts;

use Illuminate\Database\Eloquent\Model;

/**
 * Counter rows consumed by DocumentNumberService under a row lock.
 * Not intended to be written to directly from application code.
 */
class DocumentSequence extends Model
{
    protected $fillable = ['key', 'scope', 'prefix', 'next_number', 'padding'];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
            'padding' => 'integer',
        ];
    }

    public function format(int $number): string
    {
        return ($this->prefix ?? '').str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT);
    }
}
