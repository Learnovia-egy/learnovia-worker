<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerCredential extends Model
{
    protected $fillable = [
        'password_hash',
    ];
}
