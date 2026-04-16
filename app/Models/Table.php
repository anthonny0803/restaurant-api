<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'min_capacity',
        'max_capacity',
        'location',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeMatchingCapacity(Builder $query, int $seatsRequested): Builder
    {
        return $query->where('is_active', true)
            ->where('min_capacity', '<=', $seatsRequested)
            ->where('max_capacity', '>=', $seatsRequested);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
