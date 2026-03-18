<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationPolicySnapshot extends Model
{
    use HasFactory;
    protected $fillable = [
        'reservation_id',
        'cancellation_deadline_hours',
        'refund_percentage',
        'admin_fee_percentage',
        'policy_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'policy_accepted_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
