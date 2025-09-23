<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QaScore extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'rubric' => 'array',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function scorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
