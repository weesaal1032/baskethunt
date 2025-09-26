<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transcript extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'segments' => 'array',
        'confidence' => 'float',
    ];

    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }
}
