<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'description',
        'message',
        'target_type',
        'target_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];
}
