<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'rate_limit_json' => 'array',
    ];

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
