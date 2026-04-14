<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientVideo extends Model
{
    protected $fillable = ['client_id', 'video_id', 'media_path'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
