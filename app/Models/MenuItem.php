<?php

namespace App\Models;

use App\Enums\MenuCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'category',
        'is_available',
        'is_featured',
        'daily_stock',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'category' => MenuCategory::class,
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    // Relationships

    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    // Scopes

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    public function scopeByCategory(Builder $query, MenuCategory $category): Builder
    {
        return $query->where('category', $category->value);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('daily_stock')
                ->orWhere('daily_stock', '>', 0);
        });
    }
}
