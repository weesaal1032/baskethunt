<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOtpToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code_hash',
        'expires_at',
        'consumed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markConsumed(): void
    {
        if ($this->consumed_at) {
            return;
        }

        $this->forceFill([
            'consumed_at' => now(),
        ])->save();
    }
}
