<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recording extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'waveform_json' => 'array',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function transcript(): HasOne
    {
        return $this->hasOne(Transcript::class);
    }
}
