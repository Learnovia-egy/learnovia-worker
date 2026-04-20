<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerCredential extends Model
{
    protected $fillable = [
        'service_name',
        'password_hash',
    ];
}
