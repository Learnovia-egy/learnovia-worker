<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'base_url',
    ];

    public function clientVideos(): HasMany
    {
        return $this->hasMany(ClientVideo::class);
    }
}
