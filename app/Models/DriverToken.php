<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'token_id',
        'token_type',
        'token_hash',
        'is_revoked',
        'expires_at',
    ];

    protected $casts = [
        'is_revoked' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
