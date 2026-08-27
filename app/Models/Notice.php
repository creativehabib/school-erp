<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'content', 'date', 'active_status'];

    protected function casts(): array
    {
        return ['date' => 'date', 'active_status' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active_status', true)->whereDate('date', '<=', today());
    }
}
