<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use HasFactory;
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'table_id',
        'seats_requested',
        'date',
        'start_time',
        'end_time',
        'status',
        'expires_at',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'expires_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function cancellationPolicySnapshot(): HasOne
    {
        return $this->hasOne(CancellationPolicySnapshot::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('expires_at', '<=', now());
    }

    public function scopeForTable(Builder $query, int $tableId): Builder
    {
        return $query->where('table_id', $tableId);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('date', $date);
    }

    public function scopeOverlapping(Builder $query, string $startTime, string $endTime): Builder
    {
        return $query->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }
}
