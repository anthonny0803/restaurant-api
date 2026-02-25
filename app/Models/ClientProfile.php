<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProfile extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'dietary_preferences',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
